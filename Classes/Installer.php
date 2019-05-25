<?php
declare(strict_types=1);
namespace GrossbergerGeorg\KickstartExtension;

/*
 * Copyright 2019 by Georg Großberger <contact@grossberger-ge.org>
 *
 * This is free software; it is provided under the terms of the MIT License
 * See the file LICENSE or <https://opensource.org/licenses/MIT> for details
 */

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Composer\Script\Event;

/**
 * Installer
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class Installer
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * Installer constructor.
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function run(Event $event)
    {
        $io = $event->getIO();
        $installer = new static($event->getComposer(), $io);
        $extKey = basename(dirname(__DIR__));
        $extKey = str_replace(' ', '_', preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $extKey));
        $extKey = (string) $io->askAndValidate(
            'Extension key [<comment>'.$extKey.'</comment>]: ',
            function ($value) {
                $value = trim((string) $value);
                if (preg_match('/^[a-z][a-z0-9_]+[a-z0-9]$/', $value) && strpos($value, '__') === false) {
                    return $value;
                }

                throw new \RuntimeException('Invalid extension key');
            },
            null,
            $extKey
        );

        $vendor = (string) $io->askAndValidate('Vendor key: ', function ($value) {
            if (is_string($value) && preg_match('/^[a-z0-9][a-z0-9\\-]+[a-z0-9]$/', $value) && strpos($value, '--') === false) {
                return $value;
            }

            throw new \RuntimeException('Invalid vendor key');
        });

        $name = $vendor . '/' . str_replace('_', '-', $extKey);
        $name = (string) $io->askAndValidate('Composer name [<comment>'.$name.'</comment>]: ', function ($value) {
            if (is_string($value) && preg_match('/^[a-z0-9][a-z0-9\\-]+[a-z0-9]\\/[a-z][a-z0-9\\-]+[a-z0-9]$/', $value) && strpos($value, '--') === false) {
                return $value;
            }

            throw new \RuntimeException('Invalid composer name');
        }, null, $name);

        $namespace = implode('\\\\', array_map(function ($part) {
            return str_replace(' ', '', ucwords(str_replace('-', ' ', $part)));
        }, explode('/', $name))) . '\\\\';

        $title = ucwords(str_replace('_', ' ', $extKey));
        $title = (string) $io->askAndValidate('Title: [<comment>' . $extKey . '</comment>]', function ($value) {
            if (trim($value) !== '') {
                return $value;
            }

            throw new \RuntimeException('Title must not be empty');
        }, null, $title);

        $licenses = [
            'MIT',
            'Apache-2.0',
            'GPL-3.0-only',
            'GPL-3.0-or-later',
            'proprietary'
        ];
        $license = (string) $io->select('License', $licenses, 'GPL-3.0-or-later');

        if (is_numeric($license)) {
            $license = $licenses[$license];
        }

        $description = (string) $io->ask('Description: ', '');

        $installer->writeComposerManifest($extKey, $namespace, $name, $license, $description);
        $installer->writeEmConf($title, $description);
        $installer->cleanup();

        $io->write('<info>Extension skeleton created</info>');
    }

    private function writeComposerManifest(
        string $extKey,
        string $namespace,
        string $composerName,
        string $license,
        string $description
    ) {
        $composerFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'composer.json';
        $json = new JsonManipulator(file_get_contents($composerFile));
        $json->addSubNode('extra', 'installer-name', $extKey);
        $json->addMainKey('name', $composerName);
        $json->addMainKey('description', $description);
        $json->addMainKey('license', $license);
        $json->addMainKey('type', 'typo3-cms-extension');
        $json->removeMainKey('scripts');
        $json->removeSubNode('require-dev', 'composer/composer');
        $result = $json->getContents();
        $result = str_replace('GrossbergerGeorg\\\\KickstartExtension\\\\', $namespace, $result);
        file_put_contents($composerFile, $result);
    }

    private function cleanup()
    {
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        unlink($baseDir . 'composer.lock');
        unlink($baseDir . 'Classes' . DIRECTORY_SEPARATOR . 'Installer.php');
    }

    private function writeEmConf(string $title, string $description)
    {
        $confFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ext_emconf.php';
        $conf = file_get_contents($confFile);
        $conf = str_replace('<TITLE>', $title, $conf);
        $conf = str_replace('<DESCRIPTION>', $description, $conf);
        file_put_contents($confFile, $conf);
    }
}
