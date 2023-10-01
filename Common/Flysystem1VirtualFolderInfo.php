<?php
namespace axenox\FlysystemConnector\Common;

use exface\Core\DataTypes\FilePathDataType;
use \DateTimeInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\DataTypes\MimeTypeDataType;
use League\Flysystem\Filesystem;

/**
 * Contains information about a virtual folder - i.e. element of a path in file systems,
 * that do not support folders explicitly - like Azure BLOB storage.
 *
 * @author Andrej Kabachnik
 */
class Flysystem1VirtualFolderInfo extends Flysystem1FileInfo
{
    /**
     * 
     * @param Filesystem $filesystem
     * @param string $path
     * @param string $basePath
     * @param string $directorySeparator
     */
    public function __construct(Filesystem $filesystem, string $path, string $basePath = null)
    {
        $attributes = [
            'type' => 'dir',
            'path' => $path,
            'basename' => FilePathDataType::findFolder($path)
        ];
        parent::__construct($filesystem, $attributes, $basePath);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\FlysystemConnector\Common\Flysystem1FileInfo::isVirtual()
     */
    public function isVirtual() : bool
    {
        return true;
    }
}