<?php
namespace axenox\FlysystemConnector\DataConnectors;

use League\Flysystem\Filesystem;
use axenox\FlysystemConnector\Common\AbstractFlysystemConnector;
use exface\Core\Factories\GenericUxonFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Generic Flysystem connection
 * 
 * ## Examples:
 * 
 * ### Simple local adapter
 * 
 * ```
 * {
 *  "adapter": {
 *      "__class": "\\League\\Flysystem\\Local\\LocalFilesystemAdapter",
 *      "__construct": [
 *          "/root/directory/"
 *      ]
 *  }
 * }
 * 
 * ```
 * 
 * ### Local filesystem adapter with advanced configuration
 * 
 * ```
 * {
 *  "adapter": {
 *      "__class": "\\League\\Flysystem\\Local\\LocalFilesystemAdapter",
 *      "__construct": [
 *          "/root/directory/",
 *          {
 *              "__class": \\League\\Flysystem\\UnixVisibility\\PortableVisibilityConverter",
 *              "fromArray": [
 *                  {
 *                      "file": {
 *                          "public": 640,
 *                          "private": 604
 *                      },
 *                      "dir": {
 *                          "public": 740,
 *                          "private": 7604
 *                      }
 *                  }
 *              ]
 *          },
 *          {
 *              "__constant": "LOCK_EX",
 *          },
 *          {
 *              "__constant": "\\League\\Flysystem\\Local\\LocalFilesystemAdapter::DISALLOW_LINKS",
 *          }
 *      ]
 *  }
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 */
class FlysystemConnector extends AbstractFlysystemConnector
{
    private $adapterUxon = null;
    
    private $filesystem = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\FlysystemConnector\Common\AbstractFlysystemConnector::getFilesystem()
     */
    protected function getFilesystem(): Filesystem
    {
        if ($this->filesystem === null) {
            $adapter = GenericUxonFactory::createFromUxon($this->getAdapterUxon());
            $this->filesystem = new Filesystem($adapter);
        }
        return $this->filesystem;
    }
    
    /**
     * 
     * @return string
     */
    public function getAdapterUxon() : UxonObject
    {
        return $this->adapterUxon;
    }
    
    /**
     * 
     * @uxon-property adapter
     * @uxon-type object
     * @uxon-template {"__class": "", "__construct": [""]}
     * 
     * @param UxonObject $value
     * @return FlysystemConnector
     */
    protected function setAdapter(UxonObject $value) : FlysystemConnector
    {
        $this->filesystem = null;
        $this->adapterUxon = $value;
        return $this;
    }
}