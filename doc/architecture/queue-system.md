# Queue System

All indexing operations flow through a cron-driven async queue.

Jobs are persisted to `algoliasearch_queue` and processed by `Cron/ProcessQueue.php`.
Each job references a class and method that must appear in `Job::ALLOWED_HANDLERS` - a
hardcoded whitelist that prevents arbitrary code execution if the queue table is
compromised. Execution logs go to `algoliasearch_queue_log`; completed or failed jobs
are archived to `algoliasearch_queue_archive`.

The queue processes full reindex and delta jobs in a mixed ratio
(`FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33`), ensuring delta updates get at least 67%
of each processing cycle.

## Store-Scoped Job Claiming

`Queue::runCron()`, `run()`, `getJobs()` and `fetchJobs()` accept a trailing optional
`?int $storeId`. When given, the claim query filters on `store_id` in SQL next to the
existing `pid IS NULL` condition (before `FOR UPDATE`), and the recursive
`run(-1)` pass keeps the same store so a worker cannot drift into other stores during an
empty-queue run. `clearOldFailingJobs()` is scoped the same way so two workers cleaning up
different stores cannot both archive the same failed rows. `unlockStackedJobs()` stays
global (time-based and idempotent).

`Queue::discoverPendingStoreIds(): int[]` is the discovery entry point: it runs the
stale-lock cleanup itself and returns the stores that have claimable jobs, FIFO ordered by
the oldest pending job per store, so the most backlogged stores are served first. 

`Queue::hasStorelessPendingJobs(): bool` reports whether store-agnostic rows are pending 
(after applying the same cleanup first). Not intended as an in-tree use case.

**Enqueue contract.** The canonical enqueue entry point is
`Queue::addJob(JobDefinition, ?JobContext)` with the DTOs in `Model/Data/Queue/`.
`JobDefinition::$storeId` is non-nullable and is the job's single source of truth: it is
persisted in the `store_id` column and injected into the handler by parameter name
(`$storeId`) at execution time. It never enters the serialized payload, so new rows carry
storeless payloads and adjacent-job merging (same class, method and store only) cannot
cross store boundaries.

**Handler execution convention.** Jobs execute via `call_user_func_array([$model, $method], $data)`,
so payload keys bind to handler parameters by name. The queue bolts the claimed row's
`store_id` column onto the data as `storeId` before invocation. Every whitelisted handler
must name its store parameter `$storeId` and receive it from the queue.

**Deprecated `addToQueue()`.** Kept as a never-throwing shell for subclass overrides and
off-contract callers (this method will be removed in a future version). When the queue is
active it extracts any in-payload store into the `store_id` column and strips the key (the
two can never disagree), writing NULL when the payload carries no store; its use is logged
to `algolia.log`.

**Store-agnostic (NULL store_id) rows.** With `addJob()` canonical and `addToQueue()`
stripping the payload store, NULL rows can only come from third-party `addToQueue()` calls
with storeless payloads. Policy: store-scoped workers never claim NULL rows; sequential
mode (no store filter) still drains them. Legacy rows that carry their store inside the
payload keep draining: `Job::prepare()` promotes the payload store to the job only when
the column is NULL, and the column wins whenever it is populated.

## Worker Mode

`algolia:queue:process` is the CLI counterpart of store-scoped claiming. Run with store ID(s),
it acts as a per-store worker: it claims only those stores' jobs under a non-blocking per-store
lock, so the cron job and any number of workers can run concurrently without double-processing.
This is the operating mode the parallel indexing orchestrator (MAGE-1731) drives - one worker
process per store view.

Two invariants hold by convention:

- **Workers never spawn.** Parallelism comes from running one worker per store, never from a
  worker starting another process. The orchestrator must always pass a store ID; this is the
  structural recursion guard.
- **Failure isolation.** One store's failure does not abort the other stores' workers.

Workers check credentials per store, never globally, because stores can be 
configured individually. 

Without store IDs the command runs sequentially or as the orchestrator, depending on the
resolved concurrency (see Orchestrator Mode below).

## Orchestrator Mode

A no-argument `algolia:queue:process` run acts as the orchestrator when the effective maximum
parallel processes (CLI `--max-processes` override, else the admin configuration, resolved by
`Model\Queue\MaxProcessesResolver`) is greater than 1. It discovers the pending stores via
`Queue::discoverPendingStoreIds()`, spawns one child `algolia:queue:process <storeId>` worker
per store up to the resolved cap, refills the pool as workers exit, streams each child's
output, and prints a per-store summary. The command exits 1 if any worker failed.

- **Fallbacks to sequential.** `--sequential` forces the legacy sequential run and takes
  precedence over `--max-processes` and the admin configuration. The command also falls back
  when the effective maximum is 1, when process spawning is unsupported
  (`MaxProcessesResolver::isProcessSpawningSupported()`), or when store-agnostic
  (NULL store_id) rows are pending, since only the sequential path drains them.
- **Orchestrator lock.** The orchestrator takes a non-blocking lock named
  `algolia_queue_process` (no collision with the per-store names
  `algolia_queue_process_store_<id>`). If it is already held, the run warns and exits 0.
- **Discovery ordering.** `discoverPendingStoreIds()` runs `unlockStackedJobs()` itself and
  returns store IDs FIFO ordered by the oldest pending job per store. The storeless-row check
  runs after that cleanup, so stale-locked NULL rows are visible.
- **Structural recursion guard.** Children receive only a store ID, never `--max-processes`
  or `--sequential`, so a worker can never reach the spawn logic.
- **Failure signal.** A worker exits 1 when its store had failed jobs
  (`Queue::getNoOfFailedJobs(?int)`), and a killed child (null exit code) is recorded as a
  failure. The summary reports each store and the command exits 1 if any failed.
- **Accepted race.** Storeless rows enqueued after discovery are not seen by the running
  orchestrator; the next invocation's storeless check catches them.

### Output Funnel

Every line the orchestrator, worker and pool produce goes through a single
`QueueReporterInterface` instance, so the CLI (`ConsoleQueueReporter`) and cron
(`LoggerQueueReporter`, `var/log/algolia.log`) share identical code paths and differ only in
transport. 
- `info()`, `warning()`, `error()` are for the caller's own severity-classified messages.
- `log()` is reserved for relaying pre-formatted lines produced elsewhere (child worker output).
  It is passed through uninterpreted, formatter tags included. 
- Command-level exit codes stay in `Console\Command\Queue\*`; services return `bool` success.

### Worker Output Relay

`WorkerProcess` owns output structuring; the pool only spawns, polls, relays and collects exit
codes.

- Stdout and stderr each get their own `WorkerLineBuffer` (one instance per worker for its whole
  lifetime, since chunks split mid-line between polls). Complete lines are prefixed with the
  store label (`[Store 2 (English)] `) and relayed via `QueueReporterInterface::log()`.
- Ordering is guaranteed within a stream, but across stdout and stderr only at poll granularity
  (stdout lines are emitted before stderr lines for each poll).
- Prefixing applies only to orchestrator relay. A worker invoked directly from the CLI prints raw,
  unprefixed output, which is what the orchestrator then prefixes.
- Labels come from `StoreNameFetcher` (memoized, degrades to `Store N` for a
  deleted store), resolved at the point of use rather than threaded through signatures.

### Run ID Lifecycle

A run ID correlates the `algoliasearch_queue_log` rows written by all workers of a single
orchestration tick.

1. **Generated** once per tick by `QueueOrchestrator::orchestrate()`, after the lock and discovery 
  checks pass. Ticks that exit early (lock held, nothing pending, storeless fallback) generate none.
2. **Transported** to each child as the internal `--run-id=<id>` CLI option appended by
   `WorkerProcessFactory`.
3. **Recorded** by `Queue::runCron(..., $storeId, $runId)` on the log row, together with `store_id`.
4. **Rows without a run.** Sequential runs and direct worker invocations without `--run-id` leave
   both columns `NULL`, so `run_id IS NOT NULL` identifies orchestrator-tick rows. There is no
   parent row for the orchestrator itself; the shared `run_id` is the only link.

### Cron Dispatcher

The built-in cron job (`Cron\ProcessQueue`, job `algolia_queue_process` in the `algolia` group)
dispatches over the same `Service\Queue\QueueOrchestrator`. The gate is the concurrency configuration
itself (`QueueHelper::isParallelProcessingEnabled()`, i.e. the configured maximum is not `1`), so
the `config.xml` default keeps the legacy sequential run unchanged. The cron reads default scope
only, so store-scoped configuration values do not affect it. It reports through `LoggerQueueReporter`,
which also receives the prefixed child-worker lines, and never throws for store failures or the
summary: only unexpected exceptions mark `cron_schedule` failed.

**Overlap protection (Magento_Cron audit):** Magento's group lock 
(`ProcessCronQueueObserver::lockGroup()`,  name `CRON_<groupId>`) wraps only `cleanupJobs()` 
and `generateSchedules()`, not `processPendingJobs()`. With `use_separate_process=0` for the 
`algolia` group (`etc/cron_groups.xml`), the job runs in-process on every `cron:run` tick, and 
two overlapping ticks can both enter `processPendingJobs()`, so the same job can run twice 
concurrently via two different pending schedule rows. The extension's own `algolia_queue_process` 
lock inside the orchestrator is therefore the real overlap guard for parallel mode: a second 
overlapping tick logs "already running" and returns success. The legacy sequential path keeps 
its pre-existing exposure, unchanged by this work.

---

See [Architecture](../ARCHITECTURE.md) for full overview.
