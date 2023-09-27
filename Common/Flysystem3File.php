<?php
namespace axenox\FlysystemConnector\Common;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Contains information about a single file on a Flysystem abstraction filesystem.
 *
 * @author Andrej Kabachnik
 */
class Flysystem3File implements FileInterface
{
    private $fileInfo = null;
    
    private $splFileObject = null;
    
    private $filesystem = null;
    
    /**
     *
     * @param LocalFileInfo $fileInfo
     * @param string $mode
     */
    public function __construct(Flysystem3FileInfo $fileInfo)
    {
        $this->fileInfo = $fileInfo;
        $this->filesystem = $fileInfo->getFilesystem();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString()
    {
        return $this->fileInfo->__toString();
    }
    
    /**
     *
     * @return mixed|NULL
     */
    public function read() : string
    {
        return $this->filesystem->read($this->fileInfo->getPathAbsolute());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readStream()
     */
    public function readStream()
    {
        return $this->filesystem->readStream($this->fileInfo->getPathAbsolute());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::writeStream()
     */
    public function writeStream($resource): FileInterface
    {
        $this->filesystem->writeStream($this->fileInfo->getPathAbsolute(), $resource);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::write()
     */
    public function write($stringOrBinary): FileInterface
    {
        $this->filesystem->write($this->fileInfo->getPathAbsolute(), $stringOrBinary);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::getFileInfo()
     */
    public function getFileInfo(): FileInfoInterface
    {
        return $this->fileInfo;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readLine()
     */
    public function readLine(int $lineNo) : ?string
    {
        $text = $this->read();
        $lines = StringDataType::splitLines($text, $lineNo);
        return $lines[$lineNo-1];
    }
}