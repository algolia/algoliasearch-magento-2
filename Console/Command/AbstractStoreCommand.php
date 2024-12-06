<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class AbstractStoreCommand extends Command
{
    protected const STORE_ARGUMENT = 'store';

    protected ?OutputInterface $output = null;
    protected ?InputInterface $input = null;

    abstract protected function getCommandName(): string;
    abstract protected function getCommandDescription(): string;
    abstract protected function getAdditionalDefinition(): array;
    abstract protected function getStoreArgumentDescription(): string;

    public function __construct(
        protected State            $state,
        protected StoreNameFetcher $storeNameFetcher,
        ?string                    $name = null
    )
    {
        parent::__construct($name);
    }

    protected function getCommandPrefix(): string
    {
        return 'algolia:';
    }

    protected function getFullCommandName(): string
    {
        return $this->getCommandPrefix() . $this->getCommandName();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $definition = [$this->getStoreArgumentDefinition()];
        $definition = array_merge($definition, $this->getAdditionalDefinition());

        $this->setName($this->getFullCommandName())
            ->setDescription($this->getCommandDescription())
            ->setDefinition($definition);

        parent::configure();
    }

    protected function setAreaCode(): void
    {
        try {
            $this->state->setAreaCode(Area::AREA_CRONTAB);
        } catch (LocalizedException) {
            // Area code is already set - nothing to do
        }
    }

    /**
     * @param InputInterface $input
     * @return int[]
     */
    protected function getStoreIds(InputInterface $input): array
    {
        return (array) $input->getArgument(self::STORE_ARGUMENT);
    }

    protected function getStoreArgumentDefinition(): InputArgument {
        return new InputArgument(
            self::STORE_ARGUMENT,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            $this->getStoreArgumentDescription()
        );
    }

    /**
     * @param int[] $storeIds
     * @return string
     */
    protected function getOperationTargetLabel(array $storeIds): string
    {
        return ($storeIds ? count($storeIds) : 'all') . ' store' . (!$storeIds || count($storeIds) > 1 ? 's' : '');
    }

    /**
     * Generate a CLI operation announcement based on passed store arguments
     * @param string $msg Use {{target} in message as a placeholder for inserting the generated target label
     * @param int[] $storeIds
     * @return string
     * @throws NoSuchEntityException
     */
    protected function decorateOperationAnnouncementMessage(string $msg, array $storeIds): string
    {
        $msg = str_replace('{{target}}', $this->getOperationTargetLabel($storeIds), $msg);
        return ($storeIds)
            ? "<info>$msg: " . join(", ", $this->storeNameFetcher->getStoreNames($storeIds)) . '</info>'
            : "<info>$msg</info>";
    }

    protected function confirmOperation(string $okMessage = '', string $cancelMessage = 'Operation cancelled'): bool
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<question>Are you sure wish to proceed? (y/n)</question> ', false);
        if (!$helper->ask($this->input, $this->output, $question)) {
            if ($cancelMessage) {
                $this->output->writeln("<comment>$cancelMessage</comment>");
            }
            return false;
        }

        if ($okMessage) {
            $this->output->writeln("<comment>$okMessage</comment>");
        }
        return true;
    }
}
