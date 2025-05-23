<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

final class Route
{
    /**
     * @param array{0:string,1:string} $handler   [Controller::class, 'method']
     * @param string[]                 $methods
     * @param string[]                 $tags
     */
    public function __construct(
        private string  $path,
        private array   $methods,
        private array   $handler,
        private string  $name     = '',
        private string  $summary  = '',
        private array   $tags     = [],
    ) {}

    /* ---------- Getters ------------------------------------------------ */

    public function getPath()    : string  { return $this->path;    }
    public function getMethods() : array   { return $this->methods; }
    public function getHandler() : array   { return $this->handler; }
    public function getName()    : string  { return $this->name;    }
    public function getSummary() : string  { return $this->summary; }
    public function getTags()    : array   { return $this->tags;    }
}