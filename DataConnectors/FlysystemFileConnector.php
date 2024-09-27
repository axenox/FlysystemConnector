<?php
namespace axenox\FlysystemConnector\DataConnectors;

use League\Flysystem\Filesystem;
use exface\Core\Factories\GenericUxonFactory;
use exface\Core\CommonLogic\UxonObject;
use axenox\FlysystemConnector\Common\AbstractFlysystemConnector;

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
 * ## Detecting corrupted files
 * 
 * Files sometimes may break in the process of writing them - e.g. through concurrent writes,
 * file system glitches, etc. This connector allows to add some extra validations to detect
 * this via 
 * 
 * - `validations_before_writing` - e.g. try to open the image in memory
 * - `validations_before_writing` - e.g. double-check MD5 hash or try to open the saved image
 *
 * @author Andrej Kabachnik
 */
class FlysystemFileConnector extends AbstractFlysystemConnector
{
    private $adapterUxon = null;
    
    private $filesystem = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\FlysystemFileConnector\Common\AbstractFlysystemFileConnector::getFilesystem()
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
     * @return FlysystemFileConnector
     */
    protected function setAdapter(UxonObject $value) : FlysystemFileConnector
    {
        $this->filesystem = null;
        $this->adapterUxon = $value;
        return $this;
    }
}