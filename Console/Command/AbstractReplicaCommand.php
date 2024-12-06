<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class AbstractReplicaCommand extends AbstractStoreCommand
{
    abstract protected function getCommandDescription(): string;

    abstract protected function getAdditionalDefinition(): array;

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

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'replicas:';
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
