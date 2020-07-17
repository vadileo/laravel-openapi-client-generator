<?php

namespace Greensight\LaravelOpenapiClientGenerator\Core\Patchers;

class ComposerPackagePatcher extends PackageManifestPatcher {

    /**
     * @var string
     */
    protected $manifestName = 'composer.json';

    /**
     * @var string
     */
    protected $packageName;

    public function __construct(string $packageRootDir, string $packageName)
    {
        parent::__construct($packageRootDir);
        $this->packageName = $packageName;
    }

    protected function applyPatchers($manifest)
    {
        $manifest = $this->patchPackageName($manifest);
        $manifest = $this->patchLicense($manifest);
        $manifest = $this->patchRequire($manifest);

        return $manifest;
    }

    protected function patchPackageName($manifest)
    {
        $manifest['name'] = $this->packageName;
        return $manifest;
    }

    protected function patchRequire($manifest)
    {
        $manifest['require']['laravel/framework'] = '^7.10';
        return $manifest;
    }
}
