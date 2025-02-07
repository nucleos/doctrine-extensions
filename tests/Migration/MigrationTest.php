<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\Migration;

use Nucleos\Doctrine\Tests\Bridge\Symfony\App\AppKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @group integration
 */
final class MigrationTest extends KernelTestCase
{
    public function testMigrateUp(): void
    {
        $kernel = self::createKernel();
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->runCommand($application, 'doctrine:database:drop', [
            '--force' => true,
            '--quiet' => true,
        ]);
        $this->runCommand($application, 'doctrine:migrations:migrate', [
            '--no-interaction'       => true,
            '--allow-no-migration'   => true,
            '-v'                     => true,
        ]);
    }

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runCommand(Application $application, string $command, array $arguments): void
    {
        $arguments['command'] = $command;

        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput($arguments), $output);

        self::assertSame(Command::SUCCESS, $status, \sprintf('Command "%s" failed', $command));
    }
}
