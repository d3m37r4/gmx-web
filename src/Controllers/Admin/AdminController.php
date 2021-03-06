<?php

namespace GameX\Controllers\Admin;

use \GameX\Core\BaseAdminController;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \GameX\Constants\Admin\AdminConstants;
use \GameX\Core\Auth\Models\UserModel;
use \GameX\Models\Server;
use \GameX\Models\Player;
use \Carbon\Carbon;

class AdminController extends BaseAdminController
{
    
    /**
     * @return string
     */
    protected function getActiveMenu()
    {
        return AdminConstants::ROUTE_INDEX;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function indexAction(ServerRequestInterface $request, ResponseInterface $response)
    {

        $today = Carbon::today();
//		TODO: For active menu checks
//		$request->getAttribute('route')->getName();
        return $this->getView()->render($response, 'admin/index.twig', [
            'servers' => [
                'total' => Server::count(),
                'active' => Server::where('active', 1)->count(),
            ],
            'users' => [
                'total' => UserModel::count(),
                'new' => UserModel::whereDate('created_at', $today)->count()
            ],
            'players' => [
                'total' => Player::count(),
                'new' => Player::whereDate('created_at', $today)->count()
            ],
        ]);
    }
}
