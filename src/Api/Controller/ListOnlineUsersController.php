<?php

namespace FoF\OnlineUsers\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use FoF\OnlineUsers\UserRepository;
use Flarum\Api\Serializer\UserSerializer;

class ListOnlineUsersController extends AbstractListController
{
    public $serializer = UserSerializer::class;

    protected $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('viewOnlineUsersWidget');

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $result = $this->repository->getOnlineUsers($actor);
        $document->setMeta([
            'totalCount' => $result['count'],
        ]);
        return $result['users'];
    }
}