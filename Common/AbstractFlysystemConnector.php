<?php
namespace axenox\FlysystemConnector\Common;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\Interfaces\DataSources\FileDataQueryInterface;
use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\CommonLogic\DataQueries\FileWriteDataQuery;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use League\Flysystem\Filesystem;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\DataTypes\RegularExpressionDataType;

/**
 * Reads and writes files via Flysystem
 *
 * @author Andrej Kabachnik
 */
abstract class AbstractFlysystemConnector extends TransparentConnector
{
    private $base_path = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        return;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     * @param FileDataQueryInterface
     * @return FileDataQueryInterface
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof FileDataQueryInterface)) {
            throw new DataConnectionQueryTypeError($this, 'DataConnector "' . $this->getAliasWithNamespace() . '" expects an instance of FileDataQueryInterface as query, "' . get_class($query) . '" given instead!', '6T5W75J');
        }
        
        // If the query does not have a base path, use the base path of the connection
        $connectionBase = $this->getBasePath();
        if ($connectionBase !== null) {
            $queryBase = $query->getBasePath();
            switch (true) {
                case ! $queryBase:
                    $query->setBasePath($connectionBase);
                    break;
                case FilePathDataType::isAbsolute($queryBase) && FilePathDataType::isAbsolute($connectionBase):
                    if (StringDataType::startsWith($connectionBase, $queryBase)) {
                        $query->setBasePath($connectionBase);
                    } elseif (! StringDataType::startsWith($queryBase, $connectionBase)) {
                        throw new DataQueryFailedError($query, 'Cannot combine base paths of file query ("' . $queryBase .  '") and connection ("' . $connectionBase . '")');
                    }
                    break;
            }
        }
        
        if ($query instanceof FileWriteDataQuery) {
            return $this->performWrite($query);
        } else {
            return $this->performRead($query);
        }
    }
    
    /**
     *
     * @param FileReadDataQuery $query
     * @throws DataQueryFailedError
     * @return FileReadDataQuery
     */
    protected function performRead(FileReadDataQuery $query) : FileReadDataQuery
    {
        // Note: $query->getBasePath() already includes the base path of this connection
        // - see `performQuery()`
        $basePath = $query->getBasePath() ?? '';
        
        $paths = $query->getFolders(true);
        // If no paths could be found anywhere (= the query object did not have any folders defined), use the base path
        if (empty($paths)) {
            $paths[] = $basePath;
        }
        // If there are no paths at this point, we don't have any existing folder to look in,
        // so add an empty result to the finder and return it. We must call in() or append()
        // to be able to iterate over the finder!
        if (empty($paths)){
            return $query->withResult([]);
        }
        
        $namePatterns = $query->getFilenamePatterns() ?? [];
        
        try {
            $filesystem = $this->getFilesystem();
            return $query->withResult($this->createGenerator($filesystem, $paths, $namePatterns, $basePath));
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "Failed to read local files", null, $e);
        }
    }
    
    /**
     *
     * @param Finder $filesystem
     * @param string $basePath
     * @param string $directorySeparator
     * @return \Generator
     */
    protected function createGenerator(Filesystem $filesystem, array $paths, array $namePatterns = [], string $basePath = null) : \Generator
    {
        $filterRegExps = [];
        $filterNames = [];
        foreach ($namePatterns as $p) {
            if (RegularExpressionDataType::isRegex($p)) {
                $filterRegExps[] = $p;
            } else {
                $filterNames[] = $p;
            }
        }
        foreach ($paths as $path) {
            $listing = $filesystem->listContents($path);
            if ($this->getFlysystemVersion() === 1) {
                // Flysystem 1
                foreach ($listing as $arr) {
                    if (! $this->matchRegExps($arr['basename'], $filterRegExps)) {
                        continue;
                    }
                    if (! $this->matchPatterns($arr['basename'], $filterNames)) {
                        continue;
                    }
                    yield new Flysystem1FileInfo($filesystem, $arr, $basePath);
                }
            } else {
                // Flysystem 3
                foreach ($listing->getIterator() as $storageAttrs) {
                    yield new Flysystem1FileInfo($filesystem, $storageAttrs->getPath(), $basePath);
                }
            }
        }
    }
    
    /**
     * 
     * @param string $path
     * @param string[] $patterns
     * @return bool
     */
    protected function matchRegExps(string $path, array $patterns) : bool
    {
        if (empty($patterns)) {
            return true;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @param string $path
     * @param string[] $patterns
     * @return bool
     */
    protected function matchPatterns(string $path, array $patterns) : bool
    {
        if (empty($patterns)) {
            return true;
        }
        foreach ($patterns as $pattern) {
            if (FilePathDataType::matchesPattern($path, $pattern) === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     *
     * @param FileWriteDataQuery $query
     * @throws DataQueryFailedError
     * @return FileWriteDataQuery
     */
    protected function performWrite(FileWriteDataQuery $query) : FileWriteDataQuery
    {
        $fs = $this->getFilesystem();
        
        // Note: the base path of the query already includes the base path of this connection
        // - see `performQuery()`
        $basePath = $query->getBasePath();
        
        $resultFiles = [];
        foreach ($query->getFilesToSave(true) as $path => $content) {
            if ($path === null) {
                throw new DataQueryFailedError($query, 'Cannot write file with an empty path!');
            }
            
            if ($this->getFlysystemVersion() === 1) {
                $fs->put($path, $content ?? '');
                $fileInfo = new Flysystem1FileInfo($fs, $fs->getMetadata($path), $basePath);
            } else {
                $fs->write($path, $content ?? '');
                $fileInfo = new Flysystem3FileInfo($fs, $path, $basePath);
            }
            $resultFiles[] = $fileInfo;
        }
        
        $deleteEmptyFolders = $query->getDeleteEmptyFolders();
        foreach ($query->getFilesToDelete(true) as $pathOrInfo) {
            if ($pathOrInfo instanceof FileInfoInterface) {
                $path = $pathOrInfo->getPath();
                $fileInfo = $pathOrInfo;
            } else {
                $path = $pathOrInfo;
                $fileInfo = null;
            }
            
            // Do delete now
            $fs->delete($path);
            
            switch (true) {
                case $fileInfo !== null:
                    break;
                case $this->getFlysystemVersion() === 1:
                    $fileInfo = new Flysystem1FileInfo($fs, $fs->getMetadata($path), $basePath);
                    break;
                default:
                    $fileInfo = new Flysystem3FileInfo($fs, $path, $basePath);
                    break;
            }
            
            $resultFiles[] = $fileInfo;
            
            if ($deleteEmptyFolders === true) {
                $folder = $fileInfo->getFolderInfo();
                if ($folder !== null && ! $folder->isVirtual() && empty($fs->listContents($folder))) {
                    $fs->delete($folder);
                }
            }
        }
        
        return $query->withResult($resultFiles);
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getBasePath() : ?string
    {
        return $this->base_path;
    }
    
    /**
     * The base path for relative paths in data addresses.
     *
     * If a base path is defined, all data addresses will be resolved relative to that path.
     *
     * @uxon-property base_path
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\DataConnectors\FileFinderConnector
     */
    public function setBasePath($value) : AbstractFlysystemConnector
    {
        if ($value) {
            $this->base_path = FilePathDataType::normalize($value, '/');
        } else {
            $this->base_path = null;
        }
        return $this;
    }
    
    protected abstract function getFilesystem() : Filesystem;
    
    /**
     * 
     * @return int
     */
    protected function getFlysystemVersion() : int
    {
        if (method_exists(Filesystem::class, 'createDirectory')) {
            return 3;
        } else {
            return 1;
        }
    }
}