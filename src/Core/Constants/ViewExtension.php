<?php
namespace GameX\Core\Constants;

use \Twig_Extension;
use \Twig_Extension_GlobalsInterface;
use \GameX\Core\Auth\Permissions;
use \GameX\Constants\Admin\PlayersConstants;
use \GameX\Constants\Admin\PrivilegesConstants;
use \GameX\Constants\Admin\ServersConstants;

class ViewExtension extends Twig_Extension implements Twig_Extension_GlobalsInterface {
    
    protected $constants = [
        'admin' => [
            'players' => PlayersConstants::class,
            'privileges' => PrivilegesConstants::class,
            'servers' => ServersConstants::class,
        ],
    ];
    
    public function getGlobals() {
        return [
            'constants' => $this->getConstants($this->constants),
            'permissions' => [
                'ACCESS_LIST' => Permissions::ACCESS_LIST,
                'ACCESS_VIEW' => Permissions::ACCESS_VIEW,
                'ACCESS_CREATE' => Permissions::ACCESS_CREATE,
                'ACCESS_EDIT' =>  Permissions::ACCESS_EDIT,
                'ACCESS_DELETE' => Permissions::ACCESS_DELETE,
            ]
        ];
    }
    
    protected function getConstants(array $list) {
        $result = [];
        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->getConstants($value);
            } else if (class_exists($value, true)) {
                $class = new \ReflectionClass($value);
                $result[$key] = $class->getConstants();
            } else {
                throw new \Exception('Bad value ' . $value);
            }
        }
        return $result;
    }
}
