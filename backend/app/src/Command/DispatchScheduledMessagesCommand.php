<?php

namespace App\Command;

use App\Service\ScheduledMessageDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:scheduled-messages:dispatch',
    description: 'Dispatch scheduled messages whose scheduled time has come',
)]
final class DispatchScheduledMessagesCommand extends Command
{
    public function __construct(
        private readonly ScheduledMessageDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('loop', null, InputOption::VALUE_NONE, 'Run continuously, polling every --interval seconds')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Polling interval in seconds (with --loop)', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('loop')) {
            $interval = max(1, (int) $input->getOption('interval'));
            $output->writeln(sprintf('Looping every %ds. Press Ctrl+C to stop.', $interval));
            while (true) {
                $n = $this->dispatcher->dispatchDue();
                if ($n > 0) {
                    $output->writeln(sprintf('[%s] dispatched %d', (new \DateTimeImmutable())->format('c'), $n));
                }
                sleep($interval);
            }
        }

        $n = $this->dispatcher->dispatchDue();
        $output->writeln(sprintf('Dispatched %d scheduled message(s).', $n));
        return Command::SUCCESS;
    }
}
