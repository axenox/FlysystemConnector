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
        foreach ($paths as $path) {
            $listing = $filesystem->listContents($path);
            if (is_array($listing)) {
                // Flysystem 1
                foreach ($listing as $arr) {
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
     * @param FileWriteDataQuery $query
     * @throws DataQueryFailedError
     * @return FileWriteDataQuery
     */
    protected function performWrite(FileWriteDataQuery $query) : FileWriteDataQuery
    {
        // Note: the base path of the query already includes the base path of this connection
        // - see `performQuery()`
        $basePath = $query->getBasePath();
        
        $resultFiles = [];
        $fm = $this->getWorkbench()->filemanager();
        foreach ($query->getFilesToSave() as $path => $content) {
            if ($path === null) {
                throw new DataQueryFailedError($query, 'Cannot write file with an empty path!');
            }
            $path = $this->addBasePath($path, $basePath, $query->getDirectorySeparator());
            $fm->dumpFile($path, $content ?? '');
            $resultFiles[] = new LocalFileInfo($path);
        }
        
        $deleteEmptyFolders = $query->getDeleteEmptyFolders();
        foreach ($query->getFilesToDelete() as $pathOrInfo) {
            if ($pathOrInfo instanceof FileInfoInterface) {
                $path = $pathOrInfo->getPath();
                $fileInfo = $pathOrInfo;
            } else {
                $path = $pathOrInfo;
                $fileInfo = null;
            }
            
            if ($basePath !== null) {
                $path = $this->addBasePath($path, $basePath, $query->getDirectorySeparator());
            }
            
            if (! file_exists($path)) {
                continue;
            }
            
            // Do delete now
            if (is_dir($path)) {
                $fm->deleteDir($path);
            } else {
                $check = unlink($path);
                if ($check === false) {
                    throw new DataQueryFailedError($query, 'Cannot delete file "' . $pathOrInfo . '"!');
                }
            }
            
            $resultFiles[] = $fileInfo ?? new LocalFileInfo($path, $basePath, $query->getDirectorySeparator());
            
            if ($deleteEmptyFolders === true) {
                $folder = FilePathDataType::findFolderPath($path);
                if ($folder !== '' && $fm::isDirEmpty($folder)) {
                    $fm::deleteDir($folder);
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
}