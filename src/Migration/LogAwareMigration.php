<?php

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Migration;

use Psr\Log\LoggerAwareTrait;

trait LogAwareMigration
{
    use LoggerAwareTrait;

    protected function section(string $message): void
    {
        $this->writeLn(\sprintf('%s', $message));
    }

    protected function info(string $message): void
    {
        $this->writeLn(\sprintf('-> %s', $message));
    }

    protected function debug(string $message): void
    {
        $this->writeLn(\sprintf('  * %s', $message));
    }

    protected function writeLn(string $message): void
    {
        $this->logger?->notice($message, [
            'migration' => $this,
        ]);
    }
}
