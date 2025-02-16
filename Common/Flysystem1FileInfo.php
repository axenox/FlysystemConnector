<?php
namespace axenox\FlysystemConnector\Common;

use exface\Core\DataTypes\FilePathDataType;
use \DateTimeInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FileNotFoundException;
use axenox\FlysystemConnector\Interfaces\FlysystemFileInfoInterface;

/**
 * Contains information about a single file or folder in Flysystem.
 *
 * @author Andrej Kabachnik
 */
class Flysystem1FileInfo implements FlysystemFileInfoInterface
{
    private $path = null;
    
    private $basePath = null;
    
    private $pathAbs = null;
    
    private $attrs = null;
    
    private $filesystem = null;
    
    /**
     * 
     * @param Filesystem $filesystem
     * @param string $path
     * @param string $basePath
     * @param string $directorySeparator
     */
    public function __construct(Filesystem $filesystem, array $attributes, string $basePath = null)
    {
        $path = $attributes['path'];
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->basePath = $basePath;
        $this->attrs = $attributes;
        if ($basePath !== null && ! FilePathDataType::isAbsolute($path)) {
            $this->pathAbs = FilePathDataType::join([$basePath, $path]);
        } else {
            $this->pathAbs = $path;
        } 
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolder()
     */
    public function getFolderName() : ?string
    {
        return FilePathDataType::findFolder($this->getPath());
    }
    
    public function getFolderPath() : ?string
    {
        return $this->attrs['dirname'] ?? FilePathDataType::findFolderPath($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFilename()
     */
    public function getFilename(bool $withExtension = true) : string
    {
        if (array_key_exists('basename', $this->attrs)) {
            return $withExtension ? $this->attrs['basename'] : $this->attrs['filename'];
        }
        return FilePathDataType::findFileName($this->getPath(), $withExtension);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getExtension()
     */
    public function getExtension() : string
    {
        return $this->attrs['extension'] ?? FilePathDataType::findExtension($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPath()
     */
    public function getPath() : string
    {
        return $this->path;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getBasePath()
     */
    public function getBasePath() : ?string
    {
        return $this->basePath;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isPathAbsolute()
     */
    public function isPathAbsolute() : bool
    {
        return FilePathDataType::isAbsolute($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathAbsolute()
     */
    public function getPathAbsolute() : string
    {
        return $this->pathAbs;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathRelative()
     */
    public function getPathRelative() : ?string
    {
        $basePath = $this->getBasePath() ? $this->getBasePath() . $this->getDirectorySeparator() : '';
        return $basePath !== '' ? str_replace($basePath, '', $this->getPath()) : $this->getPath();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getSize()
     */
    public function getSize() : ?int
    {
        return $this->attrs['size'];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMTime()
     */
    public function getMTime() : ?int
    {
        return $this->attrs['timestamp'];
        
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCTime()
     */
    public function getCTime() : ?int
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isWritable()
     */
    public function isWritable() : bool
    {
        return $this->filesystem->has($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isReadable()
     */
    public function isReadable() : bool
    {
        return $this->filesystem->has($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isFile()
     */
    public function isFile() : bool
    {
        return $this->attrs['type'] === 'file';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isDir()
     */
    public function isDir() : bool
    {
        return $this->attrs['type'] === 'dir';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isLink()
     */
    public function isLink() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getLinkTarget()
     */
    public function getLinkTarget() : ?string
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::openFile()
     */
    public function openFile(string $mode = null) : FileInterface
    {
        return new Flysystem1File($this);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString()
    {
        return $this->getPath();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getModifiedOn()
     */
    public function getModifiedOn(): ?DateTimeInterface
    {
        if (! $this->attrs['timestamp']) {
            return null;
        }
        return new \DateTimeImmutable('@' . $this->attrs['timestamp']);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCreatedOn()
     */
    public function getCreatedOn(): ?\DateTimeInterface
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolderInfo()
     */
    public function getFolderInfo(): ?FileInfoInterface
    {
        $folderPath = $this->getFolderPath();
        if ($folderPath === null || $folderPath === '') {
            return null;
        }
        try {
            $folderArr = $this->filesystem->getMetadata($this->attrs['dirname']);
        } catch (FileNotFoundException $e) {
            // Flysystem also supports storages, that do not have folders as such - like Azure BLOB storage.
            // In these cases Flysystem emulates folders by creating file names with paths in them, which
            // is a common technique. These "virtual folders" cannot be read explicitly though, so they
            // don't have metadata. On the other hand, their contents can be read perfectly.
            // Here we assume, that any folder in an existing path, that does not exist itself, is a virtual
            // folder.
            if ($this->getFilesystem()->has($this->getPath())) {
                return new Flysystem1VirtualFolderInfo($this->filesystem, $this->attrs['dirname'], $this->getBasePath());
            }
            throw $e;
        }
        return new Flysystem1FileInfo($this->filesystem, $folderArr, $this->getBasePath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getDirectorySeparator()
     */
    public function getDirectorySeparator() : string
    {
        return '/';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMimetype()
     */
    public function getMimetype(): ?string
    {
        return $this->filesystem->getMimetype($this->getPath());
    }
    
    /**
     * 
     * @return Filesystem
     */
    public function getFilesystem() : Filesystem
    {
        return $this->filesystem;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getType()
     */
    public function getType(): string
    {
        return $this->attrs['type'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\FlysystemConnector\Interfaces\FlysystemFileInfoInterface::isVirtual()
     */
    public function isVirtual() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::exists()
     */
    public function exists(): bool
    {
        return $this->filesystem->has($this->getPath());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMd5()
     */
    public function getMd5() : ?string
    {
        if ($this->isDir() || $this->isVirtual() || ! $this->exists()) {
            return null;
        }
        $binary = $this->openFile()->read();
        if ($binary === null) {
            return null;
        }
        $hash = md5($binary);
        return $hash === false ? null : $hash;
    }
}