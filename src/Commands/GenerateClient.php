<?php

namespace Greensight\LaravelOpenapiClientGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

abstract class GenerateClient extends Command
{
    /**
     * @var string
     * Client name: js or php, must be set in child classes
     */
    protected $client;

    /**
     * @var string
     * Generator name, one of valid openapi generators names
     */
    protected $generator;

    /**
     * @var string
     */
    protected $apidocDir;

    /**
     * @var string
     */
    protected $outputDir;

    /**
     * @var string
     */
    protected $gitUser;

    /**
     * @var string
     */
    protected $gitRepo;

    /**
     * @var string
     */
    protected $gitHost;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * @var array
     */
    protected $filesToIgnoreDuringCleanup;

    public function __construct()
    {
        parent::__construct();

        $this->apidocDir = config('openapi-client-generator.apidoc_dir');
        $this->outputDir = config('openapi-client-generator.output_dir_template') . "-$this->client";

        $this->gitUser = config('openapi-client-generator.git_user', '');
        $this->gitRepo = config('openapi-client-generator.git_repo_template', '') . "-$this->client";
        $this->gitHost = config('openapi-client-generator.git_host', '');

        $this->params = config("openapi-client-generator.{$this->client}_args.params");
        $this->templateDir = config("openapi-client-generator.{$this->client}_args.template_dir", '');
        $this->filesToIgnoreDuringCleanup = config("openapi-client-generator.{$this->client}_args.files_to_ignore_during_cleanup", []);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->recursiveClearDirectory($this->outputDir);
        $this->generateClientPackage();
        $this->patchClientPackage();
        $this->copyLicenseToClientPackage();
    }

    protected abstract function patchClientPackage(): void;

    private function generateClientPackage(): void
    {
        $bin = 'npx @openapitools/openapi-generator-cli';
        $i = escapeshellarg($this->apidocDir . DIRECTORY_SEPARATOR . "index.yaml");
        $g = escapeshellarg($this->generator);
        $o = escapeshellarg($this->outputDir);
        $command = "$bin generate -i $i -g $g -o $o " . $this->getGeneratorArguments();

        $this->info("Generating $this->client client by command: $command");

        shell_exec($command);
    }

    private function getGeneratorArguments(): string
    {
        $arguments = '';

        if (Str::length($this->gitUser) > 0) {
            $arguments .= " --git-user-id " . escapeshellarg($this->gitUser);
        }

        if (Str::length($this->gitRepo) > 0) {
            $arguments .= " --git-repo-id " . escapeshellarg($this->gitRepo);
        }

        if (Str::length($this->gitHost) > 0) {
            $arguments .= " --git-host " . escapeshellarg($this->gitHost);
        }

        if (Str::length($this->templateDir) > 0) {
            $arguments .= " -t " . escapeshellarg($this->templateDir);
        }

        $additionalParams = $this->getAdditionalParamsArgument();

        if (Str::length($additionalParams) > 0) {
            $arguments .= " -p " . escapeshellarg($additionalParams);
        }

        return $arguments;
    }

    private function getAdditionalParamsArgument(): string
    {
        return collect($this->params)
            ->map(function ($value, $name) {
                $escapedValue = PHP_OS_FAMILY !== 'Windows' ? str_replace("\\", "\\\\", $value) : $value;
                return "$name=$escapedValue";
            })
            ->join(',');
    }

    private function copyLicenseToClientPackage(): void
    {
        $source = $this->templatePath('LICENSE-template.md');
        $dest = $this->outputDir . DIRECTORY_SEPARATOR . 'LICENSE.md';
        if (!file_exists($dest)) {
            copy($source, $dest);
            $this->info("Template LICENSE.md copied to package");
        }
    }

    protected function templatePath(string $path): string
    {
        return __DIR__ . '/../../templates/' . ltrim($path, '/');
    }

    private function recursiveClearDirectory(string $dir, int $level = 0)
    {
        if (!$dir) {
            return;
        }

        foreach (scandir($dir) as $fileWithoutDir) {
            if (in_array($fileWithoutDir, ['..', '.'])) {
                continue;
            }
            $file = $dir . "/" . $fileWithoutDir;

            if ($level === 0 && in_array($fileWithoutDir, $this->filesToIgnoreDuringCleanup)) {
                continue;
            }

            if (is_dir($file)) {
                $this->recursiveClearDirectory($file, $level + 1);
            } else {
                unlink($file);
            }
        }

        if ($level > 0) {
            rmdir($dir);
        }
    }
}
