<?php
namespace axenox\FlysystemConnector\Interfaces;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;

interface FlysystemFileInfoInterface extends FileInfoInterface
{
    /**
     * Returns TRUE if this is a virtual file/folder that does not really exist in the file system,
     * but is emulated by Flysystem.
     *
     * For example, Flysystem supports storages, that do not have folders as such - like Azure BLOB storage.
     * In these cases Flysystem emulates folders by creating file names with paths in them, which
     * is a common technique. These "virtual folders" cannot be read explicitly though, so they
     * don't have metadata. On the other hand, their contents can be read perfectly.
     *
     * @return bool
     */
    public function isVirtual() : bool;
}