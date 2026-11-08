<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use Phinx\Console\Command\Create;
use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMigrationCommand extends Create
{
    protected function configure(): void
    {
        parent::configure();

        $this->setName('create');
        $this->setDescription('Create a new migration from a snake_case name');
        $this->getDefinition()->setArguments([
            new InputArgument('name', InputArgument::REQUIRED, 'Migration name in snake_case, for example add_sample_status'),
        ]);
        $this->setHelp('Example: composer make:migration -- add_sample_status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (is_string($name) && preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D', $name)) {
            // Phinx discovers PascalCase PHP classes from snake_case filenames.
            $input->setArgument('name', str_replace('_', '', ucwords($name, '_')));
        } elseif (!is_string($name) || !Util::isValidPhinxClassName($name)) {
            throw new InvalidArgumentException('Use a snake_case migration name, for example add_sample_status.');
        }

        return parent::execute($input, $output);
    }
}
