# Contributing to Algolia for Magento 2

Contributions to the codebase are done using the fork & pull model.
This contribution model has contributors maintaining their own copy of the forked codebase (which can easily be synced with the main copy). The forked repository is then used to submit a request to the base repository to “pull” a set of changes (hence the phrase “pull request”).

Contributions can take the form of new components/features, changes to existing features, tests, bug fixes, optimizations or just good suggestions.

The development team will review all issues and contributions submitted by the community. During the review we might require clarifications from the contributor.


## Contribution requirements

1. Contributions must pass [Continous Integration checks]().
2. Pull requests (PRs) have to be accompanied by a meaningful description of their purpose. Comprehensive descriptions increase the chances of a pull request to be merged quickly and without additional clarification requests.
3. Commits must be accompanied by meaningful commit messages.
4. PRs which include bug fixing, must be accompanied with step-by-step description of how to reproduce the bug.
5. PRs which include new logic or new features must be submitted along with:
	* Integration test coverage
	* Proposed [documentation](https://community.algolia.com/magento/) update. Documentation contributions can be submitted [here](https://github.com/algolia/magento).
6. All automated tests are passed successfully (all builds on [Travis CI](https://travis-ci.org/algolia/algoliasearch-magento-2/) must be green).

## Contribution process

If you are a new GitHub user, we recommend that you create your own [free github account](https://github.com/signup/free). By doing that, you will be able to collaborate with the Magento 2 development team, “fork” the Magento 2 project and be able to easily send “pull requests”.

1. Fork the repository according to [Fork instructions](https://help.github.com/articles/fork-a-repo/)
2. Create and test your work.
3. When you are ready, send us a pull request – follow [Create a pull request instructions](https://help.github.com/articles/about-pull-requests/).
4. Once your contribution is received, the development team will review the contribution and collaborate with you as needed to improve the quality of the contribution.

## Continuous Integration checks

Automated continous integration checks are run on [Travis CI](https://travis-ci.org/algolia/algoliasearch-magento-2/).

As of today the automated checks are run agains Magento 2.0.* and Magento 2.1.*.
Version for Magento 2.2.* is being prepared.

### Integration tests

Integration tests are run via [PHPUnit](https://phpunit.de/) and the extension follows [Magento 2 framework](https://devdocs.magento.com/guides/v2.2/test/integration/integration_test_execution.html) to run integration tests. 

#### Setup

1. Copy test's database config to Magento integration tests directory
	```bash
	cp [[extension_root_dir]]/dev/tests/install-config-mysql.php [[magento_root_dir]]/dev/tests/integration/etc/install-config-mysql.php
	```
2. Fill the correct DB credentials to the newly created config file

#### Run

```bash
$ cd [[magento_root_dir]]/dev/tests/integration
$ ../../../vendor/bin/phpunit ../../../vendor/algolia/algoliasearch-magento-2/Test
```

### Coding Style

To check the coding style the extension uses [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).

The fixer follow Magento 2 default rules and in addition some extra rules defined by the extension's development team. The concrete rules can be found here:
- Magento's default rules - can be found in the root directory of Magento 2 installation in `.php_cs.dist` file
- [Extension's rules](https://github.com/algolia/algoliasearch-magento-2/blob/master/.php_cs)

Definitions of each rule can be found in [the documentation of PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer#usage). 

#### Run

**Check:**
```bash
$ cd [[magento_root_dir]]
$ php vendor/bin/php-cs-fixer fix vendor/algolia/algoliasearch-magento-2 --config=vendor/algolia/algoliasearch-magento-2/.php_cs -v --using-cache=no --allow-risky=yes --dry-run
```

**Fix:**
```bash
$ cd [[magento_root_dir]]
$ php vendor/bin/php-cs-fixer fix vendor/algolia/algoliasearch-magento-2 --config=vendor/algolia/algoliasearch-magento-2/.php_cs -v --using-cache=no --allow-risky=yes
```

### Static analysis

For static analysis check the extension uses [PHPStan](https://github.com/phpstan/phpstan).
PHPStan runs a static analysis of the code and is able to reveal and catch bug which cannot be easily spotted.
At the same time it helps to keep the code readable and easier to understand.

#### Run

```bash
$ cd [[magento_root_dir]] 
$ ./vendor/bin/phpstan analyse -c vendor/algolia/algoliasearch-magento-2/phpstan-travis-[[magento_version]].neon --level max vendor/algolia/algoliasearch-magento-2
```

`[[magento_version]]` can have values:
- `2.0` for Magento 2.0.*
- `2.1` for Magento 2.1.*
