<?php
namespace ZktSn0w\Inertia\Service;

use Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\ResourceManager;

#[Flow\Scope('singleton')]
class InertiaAssetVersionService {

        /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;


    #[Flow\InjectConfiguration(path: "assetVersioning.strategy", package: "ZktSn0w.Inertia")]
    protected $assetVersioningStrategy;

    #[Flow\InjectConfiguration(path: "assetVersioning.filePath", package: "ZktSn0w.Inertia")]
    protected $assetVersioningFilePath;

    #[Flow\InjectConfiguration(path: "assetVersioning.version", package: "ZktSn0w.Inertia")]
    protected $assetVersion;

    public function getAssetVersion(): ?string {
        if(!isset($this->assetVersioningStrategy) || empty($this->assetVersioningStrategy)) {
            throw new Exception("ZktSn0w.Inertia: Asset versioning strategy not set.");
        }

        switch ($this->assetVersioningStrategy) {
            case 'FILE':
                if(!isset($this->assetVersioningFilePath) || empty($this->assetVersioningFilePath)) {
                    throw new Exception("ZktSn0w.Inertia: Asset versioning file not set.");
                }

                // $assetVersionFile = $this->resourceManager->importResource($this->assetVersioningFilePath);

                // if(!isset($this->assetVersionFile)) {
                //     throw new Exception("ZktSn0w.Inertia: Asset versioning file does not exist.");
                // }

                // $assetVersionFileContent = stream_get_contents($assetVersionFile->getStream());
                // return $assetVersionFileContent;

                $assetVersionFile = file_get_contents($this->assetVersioningFilePath);
                return $assetVersionFile;

            case 'SETTING':
                if(!isset($this->assetVersion) || empty($this->assetVersion)) {
                    throw new Exception("ZktSn0w.Inertia: Asset versioning strategy set to `SETTING` but version setting was not defined or is empty.");
                }
                return $this->assetVersion;
            default:
                return null;
        }
    }
}
