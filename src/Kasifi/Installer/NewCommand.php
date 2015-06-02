<?php

namespace Kasifi\Installer;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Installer\Exception\AbortException;

/**
 * This command override existing one by managing a vagrant box
 *
 * @author Lucas CHERIFI <lucas@cherifi.info>
 */
class NewCommand extends \Symfony\Installer\NewCommand
{
    protected $vmIp;
    protected $githubOauthToken;
    protected $ramSize;

    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Symfony project.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory where the new project will be created.')

            ->addArgument('vm_ip', InputArgument::REQUIRED, 'The IP to use for the VM')

            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to be installed (defaults to the latest stable version).', 'latest')

            ->addArgument('ram_size', InputArgument::OPTIONAL, 'The RAM to use for this VM in Mo (defaults: 4000).', 4000)

        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->vmIp = trim($input->getArgument('vm_ip'));

        $this->githubOauthToken = $this->getHostGithubOauthToken();

        $this->ramSize = trim($input->getArgument('ram_size'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        try {
            $this
                ->copyFiles()
                ->customizeFiles()
            ;
        } catch (AbortException $e) {
            aborted:

            $output->writeln('');
            $output->writeln('<error>Aborting download and cleaning up temporary directories.</>');

            $this->cleanUp();

            return 1;
        } catch (\Exception $e) {
            // Guzzle can wrap the AbortException in a GuzzleException
            if ($e->getPrevious() instanceof AbortException) {
                goto aborted;
            }

            $this->cleanUp();
            throw $e;
        }
    }

    protected function getDataSourcePath() {
        $dataPath = __DIR__
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'Resources'
            .DIRECTORY_SEPARATOR.'Data';
        $dataPath = realpath($dataPath);
        return $dataPath;
    }

    protected function copyFiles()
    {
        $this->recursiveCopy($this->getDataSourcePath(), $this->projectDir);
        return $this;
    }

    protected function customizeFiles()
    {
        $replaces = [
            'ansible/files/hosts-vagrant' => [
                '%IP%' => $this->vmIp,
                '%SLUG%' => $this->projectName,
            ],
            'ansible/inventories/vm' => [
                '%IP%' => $this->vmIp
            ],
            'ansible/vars/local.yml' => [
                '%GITHUB_AUTH_TOKEN%' => $this->githubOauthToken
            ],
            'ansible/playbook.yml' => [
                '%SLUG%' => $this->projectName,
            ],
            'Vagrantfile' => [
                '%IP%' => $this->vmIp,
                '%SLUG%' => $this->projectName,
                '%RAM_SIZE%' => $this->ramSize,
            ],
        ];

        foreach ($replaces as $filename => $keys) {
            foreach ($keys as $search => $replace) {
                $this->replaceInFile($search, $replace, $filename);
            }
        }
        return $this;
    }

    protected function replaceInFile($search, $replace, $targetFilePath)
    {
        $filePath = $this->projectDir.DIRECTORY_SEPARATOR.$targetFilePath;
        if ($this->fs->exists($filePath)) {
            $content = file_get_contents($filePath);
            $content = str_replace($search, $replace, $content);
            $this->fs->dumpFile($filePath, $content);
        } else {
            throw new IOException(sprintf('File "%s" does not exists.', $filePath));
        }
        return $this;
    }

    protected function recursiveCopy($source, $dest) {

        $this->fs->mkdir($dest, 0755);
        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST) as $item
        ) {
            if ($item->isDir()) {
                $this->fs->mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                $this->fs->copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    protected function getHostGithubOauthToken()
    {
        $cmd = 'cat ~/.composer/auth.json';
        $content = `$cmd`;
        $json = json_decode($content, true);
        if (!$json) {
            throw new \Exception('You have to get a Github Oauth token configured on your host in ~/.composer/auth.json.
            And it seems not. To get one, please read https://getcomposer.org/doc/articles/troubleshooting.md#api-rate-limit-and-oauth-tokens.');
        }
        $token = $json['github-oauth']['github.com'];

        return $token;
    }
}
