<?php

namespace Kasifi\Installer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This command override existing one by managing a vagrant box
 *
 * @author Lucas CHERIFI <lucas@cherifi.info>
 */
class SelfUpdateCommand extends \Symfony\Installer\SelfUpdateCommand
{
    /** @var Filesystem */
    private $fs;

    /** @var OutputInterface */
    private $output;

    private $tempDir;

    /** @var string  the URL where the latest installer version can be downloaded */
    private $remoteInstallerFile;

    /** @var string the filepath of the installer currently installed in the local machine */
    private $currentInstallerFile;

    /** @var string the filepath of the new installer downloaded to replace the current installer */
    private $newInstallerFile;

    /** @var string the filepath of the backup of the current installer in case a rollback is performed */
    private $currentInstallerBackupFile;

    /** @var bool flag which indicates that, in case of a rollback, it's safe to restore the installer backup because it corresponds to the most recent version */
    private $restorePreviousInstaller;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->output = $output;

        $this->remoteInstallerFile = 'http://kasifi.com/sfvg/installer';
        $this->currentInstallerFile = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        $this->tempDir = sys_get_temp_dir();
        $this->currentInstallerBackupFile = basename($this->currentInstallerFile, '.phar').'-backup.phar';
        $this->newInstallerFile = $this->tempDir.'/'.basename($this->currentInstallerFile, '.phar').'-temp.phar';
        $this->restorePreviousInstaller = false;
    }


    protected function installerIsUpdated()
    {
        $isUpdated = false;
        $localVersion = $this->getApplication()->getVersion();

        if (false === $remoteVersion = @file_get_contents('kasifi.com/sfvg/version')) {
            throw new \RuntimeException('The new version of the Symfony Vagrant Installer couldn\'t be downloaded from the server.');
        }

        if ($localVersion === $remoteVersion) {
            $this->output->writeln('<info>Symfony Installer is already up to date.</info>');
            $isUpdated = true;
        } else {
            $this->output->writeln(sprintf('// <info>updating</info> Symfony Vagrant Installer to <comment>%s</comment> version', $remoteVersion));
        }

        return $isUpdated;
    }
}
