<?php
require 'vendor/autoload.php';

$config = [
    'displayErrorDetails' => true,
    'db' => [
        'host'   => 'localhost',
        'user'   => 'root',
        'pass'   => '',
        'dbname' => 'agendaiw',
    ],
];

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

$container['link'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

function CreateUserSessions($link, $data) {
    $token = '';
    $characters = 'abcdefghijklmnopqrstuvwxyz';

    for ($i = 0; $i < 13; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }

    $prep = $link->prepare('INSERT INTO `users_sessions` (`userid`, `token`) VALUES (:userid, :token)');

    $result = $prep->execute([
        'userid' => $data['userid'],
        'token' => $token,
    ]);

    if ($result == false) {
        return [ 'error' => 'Error', ];
    }

    return [
        'token' => $token,
    ];
}

$auth = function ($req, $res, $next) {
    $link = $this->link;

    $prep = $link->prepare('SELECT `userid` FROM `users_sessions` WHERE `token` = :token');

    $prep->execute([
        'token' => $req->getHeader('Authorization')[0],
    ]);

    if ($prep->rowCount() === 0) {
        return $res->withJson([ 'error' => 'User not logged', ]);
    }

    $users_sessions = $prep->fetch();

    $req = $req->withAttribute('userid', (int) $users_sessions['userid']);

    return $next($req, $res);
};

$app->post('/users', function ($req, $res) {
    $body = $req->getParsedBody();
    
    $link = $this->link;

    $prep = $link->prepare('SELECT `id` FROM `users` WHERE `email` = :email');

    $prep->execute([ 'email' => $body['email'], ]);

    if ($prep->rowCount() !== 0) {
        return $res->withJson([ 'error' => 'This e-mail is already in use', ]);
    }

    $prep = $link->prepare('INSERT INTO `users` (`name`, `email`, `password`) VALUES (:name, :email, :password)');

    $result = $prep->execute([
        'name' => $body['name'],
        'email' => $body['email'],
        'password' => $body['password'],
    ]);

    if ($result === false) {
        return $res->withJson([ 'error' => 'Error' ]);
    }

    $userId = $link->lastInsertId();

    $users_sessions = CreateUserSessions($link, [
        'userid' => $userId,
    ]);

    if (isset($users_sessions['error'])) {
        return $res->withJson([ 'error' => $users_sessions['error'], ]);
    }

    return $res->withJson([ 'token' => $users_sessions['token'], ]);
});

$app->post('/users/sessions', function ($req, $res) {
    $body = $req->getParsedBody();
    
    $link = $this->link;

    $prep = $link->prepare('SELECT `id` FROM `users` WHERE `email` = :email AND `password` = :password');

    $prep->execute([
        'email' => $body['email'],
        'password' => $body['password'],
    ]);

    if ($prep->rowCount() === 0) {
        return $res->withJson([ 'error' => 'No user was found', ]);
    }

    $user = $prep->fetch();

    $users_sessions = CreateUserSessions($link, [
        'userid' => $user['id'],
    ]);

    if (isset($users_sessions['error'])) {
        return $res->withJson([ 'error' => $users_sessions['error'], ]);
    }

    return $res->withJson([ 'token' => $users_sessions['token'], ]);
});

$app->post('/events', function ($req, $res) {
    $body = $req->getParsedBody();
    $userId = $req->getAttribute('userid');

    $link = $this->link;

    $prep = $link->prepare('INSERT INTO `events` (`userid`, `title`, `description`, `start_date`, `end_date`) VALUES (:userid, :title, :description, :start_date, :end_date)');

    $prep->execute([
        'userid' => $userId,
        'title' => $body['title'],
        'description' => $body['description'],
        'start_date' => $body['start_date'],
        'end_date' => $body['end_date'],
    ]);

    $eventId = $link->lastInsertId();

    if (count($body['relations']) >= 1) {
        $prep = $link->prepare('INSERT INTO `events_relations` (`eventid`, `type`, `targetid`) VALUES (:eventid, :type, :targetid)');

        foreach($body['relations'] as $relation) {
            $prep->execute([
                'eventid' => $eventId,
                'type' => $relation['type'],
                'targetid' => $relation['id'],
            ]);
        }
    }

    return $res->withJson([ 'id' => $eventId, ]);
})->add($auth);

$app->get('/events', function ($req, $res) {
    $userId = $req->getAttribute('userid');

    $link = $this->link;

    $prep = $link->prepare('SELECT *
        FROM events
        WHERE userid = :userid

        UNION

        SELECT events.*
        FROM events
        LEFT JOIN events_relations ON events_relations.eventid = events.id
        WHERE events_relations.type = \'user\' AND events_relations.targetid = :userid

        UNION

        SELECT events.*
        FROM users
        LEFT JOIN groups_users ON users.id = groups_users.userid
        LEFT JOIN events_relations ON groups_users.groupid = events_relations.targetid
        LEFT JOIN events ON events_relations.eventid = events.id
        WHERE events_relations.type = \'group\' and users.id = :userid');
    
    $prep->execute([ 'userid' => $userId, ]);

    $events = $prep->fetchAll();

    function GetUserBasicInfos($link, $data) {
        $prep = $link->prepare('SELECT `id`, `name` FROM `users` WHERE `id` = :id');

        $prep->execute([ 'id' => $data['userid'], ]);

        $user = $prep->fetch();

        return [
            'id' => $user['id'],
            'name' => $user['name'],
        ];
    }

    function GetGroupBasicInfos($link, $data) {
        $prep = $link->prepare('SELECT `id`, `name` FROM `groups` WHERE `id` = :id');

        $prep->execute([ 'id' => $data['groupid'], ]);

        $user = $prep->fetch();

        return [
            'id' => $user['id'],
            'name' => $user['name'],
        ];
    }

    $events = array_map(function ($event) use ($link) {
        $prep = $link->prepare('SELECT `type`, `targetid` FROM `events_relations` WHERE `eventid` = :eventid');

        $prep->execute([ 'eventid' => $event['id'], ]);

        $relations = $prep->fetchAll();

        $relations = array_map(function ($relation) use ($link) {
            $data = [];

            if ($relation['type'] === 'user') {
                $data = GetUserBasicInfos($link, [ 'userid' => $relation['targetid'], ]);
            } elseif ($relation['type'] === 'group') {
                $data = GetGroupBasicInfos($link, [ 'groupid' => $relation['targetid'], ]);
            }

            return array_merge([ 'type' => $relation['type'], ], $data);
        }, $relations);

        return [
            'id' => $event['id'],
            'user' => GetUserBasicInfos($link, [ 'userid' => $event['userid'], ]),
            'title' => $event['title'],
            'description' => $event['description'],
            'start_date' => $event['start_date'],
            'end_date' => $event['end_date'],
            'relations' => $relations,
            'created_at' => $event['created_at'],
        ];
    }, $events);

    return $res->withJson($events);
})->add($auth);

$app->post('/groups', function ($req, $res) {
    $body = $req->getParsedBody();
    $userId = $req->getAttribute('userid');

    $link = $this->link;

    $prep = $link->prepare('INSERT INTO `groups` (`userid`, `name`) VALUES (:userid, :name)');

    $prep->execute([
        'userid' => $userId,
        'name' => $body['name'],
    ]);

    $groupId = $link->lastInsertId();

    if (count($body['users']) >= 1) {
        $prep = $link->prepare('INSERT INTO `groups_users` (`groupid`, `userid`) VALUES (:groupid, :userid)');

        foreach ($body['users'] as $user) {
            $prep->execute([
                'groupid' => $groupId,
                'userid' => $user,
            ]);
        }
    }

    return $res->withJson([ 'id' => $groupId, ]);
})->add($auth);

$app->get('/search', function ($req, $res) {
    $params = $req->getParams();

    $link = $this->link;

    $prep = $link->prepare('SELECT \'user\' AS `type`, `name`, `id` FROM `users` WHERE `name` LIKE :name
    UNION
    SELECT \'group\' AS `type`, `name`, `id` FROM `groups` WHERE `name` LIKE :name');

    $prep->execute([ 'name' => '%' . $params['name'] . '%' ]);

    $rows = $prep->fetchAll();

    return $res->withJson($rows);
})->add($auth);

$app->run();