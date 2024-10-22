<?php

namespace app\service\Storage;

use app\service\Storage\Providers\Local;
use app\service\Storage\Providers\QiniuCloud;
use app\service\Storage\Providers\TencentCloud;
use Exception;

class StorageService
{
    
    protected string $storage;
    
    protected array $storageSettings;
    
    public function __construct($storage, $storageSettings)
    {
        $this->storage         = $storage;
        $this->storageSettings = $storageSettings;
    }
    
    /**
     * @throws Exception
     */
    public function upload($file, $isPublic = TRUE): bool|array
    {

        $driver = match ($this->storage) {
            'qiniu' => new QiniuCloud(),
            'tencent' => new TencentCloud(),
            default => new Local($isPublic),
        };
        $res = $driver->setSettings($this->storageSettings)
                      ->setFile($file)
                      ->uploadFile();
        
        if ($res) return $res;
        
        throw new Exception($driver->getError());
    }
}