<?php

    // Script name: DDLE
    // Author: Vitaliy Orlov, https://it-rem.ru
    // With this script you can easily set random dates for DLE posts

    $useHttpBasicAuth = true;
    $httpBasicAuthAllowedUsers = [ // <username> => <password>
        'admin' => 'ddle'
    ];

    $ver = '1.0.0';

    set_time_limit(0);

    class db {}

    class ddd_db {

        public $link = null;

        public function __construct($DBHOST,$DBUSER, $DBPASS, $DBNAME) {
            $this->link = mysqli_connect($DBHOST,$DBUSER, $DBPASS, $DBNAME);
            return $this->link;
        }

        public function exec($sql){
            $ret = mysqli_query($this->link, $sql);
            if ($ret === false) {
                $err = mysqli_error($this->link);
                if ($err) throw new Exception($err);
            }
            return $ret;
        }

        public function getRows($sql){
            $ret = null;
            if ($result = $this->exec($sql)) {
                $ret = [];
                if (mysqli_num_rows($result)>0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $ret[] = $row;
                    }
                }
                mysqli_free_result($result);
            }
            return $ret;
        }

        public function getRow($sql){
            $ret = null;
            if ($result = $this->exec($sql)) {
                $ret = [];
                if (mysqli_num_rows($result)>0) {
                    $ret = mysqli_fetch_assoc($result);
                }
                mysqli_free_result($result);
            }
            return $ret;
        }

        public function getFirst($sql){
            $ret = null;
            if ($row = $this->getRow($sql)) {
                $ret = array_shift($row);
            }
            return $ret;
        }

    }

    class DDD_Request {
        public function post($var, $default=null) {
            return isset($_POST[$var])
                   ? $_POST[$var]
                   : $default;
        }

        public function form($var=null, $default=null){

            if (is_null($var)) {
                return isset($_POST['form'])
                    ? $_POST['form']
                    : $default;

            }

            return isset($_POST['form'][$var])
                   ? $_POST['form'][$var]
                   : $default;

        }
    }

    $db = null;

    if (file_exists('engine/data/dbconfig.php')) {
        require_once('engine/data/dbconfig.php');
        $db = new ddd_db(DBHOST, DBUSER, DBPASS, DBNAME);
    }

    $request = new DDD_Request;

    // -------------------------------------------------------------------------------------------
    if ($useHttpBasicAuth) {
        $realm = 'Restricted area';

        $valid_passwords = $httpBasicAuthAllowedUsers;
        $valid_users = array_keys($valid_passwords);

        $user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
        $pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

        $validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);

        if (!$validated) {
            header('WWW-Authenticate: Basic realm="My Realm"');
            header('HTTP/1.0 401 Unauthorized');
            die ("Not authorized");
        }
    }

    // -------------------------------------------------------------------------------------------

    $form = [

        'date_from' =>  $request->form('date_from', date('Y-m-d', strtotime('-1 year'))),
        'date_to' => $request->form('date_to', date('Y-m-d', strtotime('+1 year'))),

        'use_limits' => $request->form('use_limits'),
        'offset' => $request->form('offset'),
        'limit' => $request->form('limit'),

        'use_sort' => $request->form('use_sort'),
        'sort' => $request->form('sort', 'id_asc'),

        'use_sql_append_select' => $request->form('use_sql_append_select'),
        'use_sql_append_update' => $request->form('use_sql_append_update'),

        'sql_append_select' => $request->form('sql_append_select'),
        'sql_append_update_set' => $request->form('sql_append_update_set'),
        'sql_append_update_where' => $request->form('sql_append_update_where'),
    ];

    $totalPosts = 0;

    if ($db && $db->link && !$request->form()) {
        $totalPosts = $db->getFirst('SELECT COUNT(id) FROM '.PREFIX.'_post');
        $form['limit'] = $totalPosts;
    }

    $sqlSelectionCode = 'SELECT id FROM '.PREFIX.'_post';

    if ($form['use_sql_append_select']) {
        $sqlSelectionCode .= PHP_EOL.trim($form['sql_append_select']);
    }

    if ($form['use_sort']) {
        $sqlSelectionCode .= PHP_EOL.' ORDER BY ';
        switch ($form['sort']) {
            default:
            case 'id_asc': $sqlSelectionCode .= 'id ASC'; break;
            case 'rand': $sqlSelectionCode .= 'RAND()'; break;
            case 'id_desc': $sqlSelectionCode .= 'id DESC'; break;
        }
    }

    if ($form['use_limits']) {
        $sqlSelectionCode .= PHP_EOL.' LIMIT '.intval($form['offset']).','.intval($form['limit']);
    }

    $sqlUpdateCode = 'UPDATE '.PREFIX.'_post SET';
    $sqlUpdateCode .= PHP_EOL.'date = "{{NEW_RANDOM_DATE}}"';

    if ($form['use_sql_append_update']) {
        $sqlUpdateCode .= PHP_EOL.trim($form['sql_append_update_set']);
    }

    $sqlUpdateCode .= PHP_EOL.'WHERE id = {{POST_ID}}';

    if ($form['use_sql_append_update']) {
        $sqlUpdateCode .= PHP_EOL.trim($form['sql_append_update_where']);
    }

    $sqlUpdateCode .= PHP_EOL.'LIMIT 1';

    $submitExecSqlResult = null;

    if ($request->form('submit_exec_sql')) {
        $posts = $db->getRows($sqlSelectionCode);
        $fromTs = strtotime($request->form('date_from'));
        $toTs = strtotime($request->form('date_to')) + 24*60*60 - 1;
        foreach($posts as $post) {
            $randomDateTs = rand($fromTs, $toTs);
            $sql = str_replace(
                ['{{NEW_RANDOM_DATE}}','{{POST_ID}}'],
                [date('Y-m-d H:i:s',$randomDateTs), $post['id']],
                $sqlUpdateCode
            );
            try {
                $db->exec($sql);
                $submitExecSqlResult = true;
            } catch(Exception $ex) {
                $submitExecSqlResult = $ex->getMessage().PHP_EOL.PHP_EOL.$sql;
            }
        }
    }

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>DDLE</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/css/datepicker.css">

    <style>
        h1 a, h1 a:hover {color:black; text-decoration:none;}
        .mt15 {margin-top:15px;}
        .mb15 {margin-bottom:15px;}
        .ml15 {margin-left:15px;}
        .w250 {width:250px;}
        .w100p {width:100%;}
        .flex {display:flex;}
        .flex-1 {flex:1;}
        code {display:block; padding:5px;}
        body {padding-bottom:25px;}
        .copy {font-size: 0.9em; margin: 0 -15px; padding: 5px 15px; background-color:#444; text-align:center;}
        .copy a {color:white; text-decoration:none;}
        .copy a:hover {color:#eee; text-decoration:none;}

    </style>
</head>
<body>

    <div class="container">

        <div class="row">
            <div class="col-md-12">
                <h1 class="page-header"><a href="?">DDLE by VO <?=$ver?></a></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3" style="background-color: #F0F0F0;">
                <h3>Инфо</h3>

                Подключение к БД:
                <?php if  (!$db || !$db->link):?>
                    <span class="badge" style="background-color:red;">Нет</span>
                <?php else: ?>
                    <span class="badge" style="background-color:green;">Есть</span>
                <?php endif ?>
                <br>

                <?php if  ($db && $db->link):?>
                    Всего постов: <span class="badge" ><?=intval($totalPosts)?></span><br>
                <?php endif ?>

                <br>

                <div class="copy"><a href="https://www.it-rem.ru" target="_blank">DDLE by VO [it-rem.ru]</a></div>

            </div>
            <div class="col-md-9">

                <?php if  (!$db || !$db->link):?>
                    <?php if (!file_exists('engine/data/dbconfig.php')):?>
                        Файл <strong>engine/data/dbconfig.php</strong> не найден
                    <?php else:?>
                        Не могу подключиться используя параметры:<br>
                        DBHOST: <?=DBHOST?><br>
                        DBUSER: <?=DBUSER?><br>
                        DBPASS: <?=DBPASS?><br>
                        DBNAME: <?=DBNAME?><br>
                    <?php endif;?>
                <?php else: ?>

                    <h3>Изменение дат постов</h3>
                    <form action="?" method="post">
                    <div class="flex mb15">
                        <div class="w250">
                            Новая дата, от даты:<br>
                            <div class="datepicker input-group date" data-date-format="yyyy-mm-dd">
                                <input type="text" name="form[date_from]" readonly value="<?=htmlspecialchars($form['date_from'])?>" placeholder="гггг-мм-дд"><br>
                                <span class="input-group-addon"><i class="glyphicon glyphicon-calendar"></i></span>
                            </div>

                        </div>
                        <div class="ml15 w250">
                            Новая дата, до даты:<br>
                            <div class="datepicker input-group date" data-date-format="yyyy-mm-dd">
                                <input type="text" name="form[date_to]" readonly value="<?=htmlspecialchars($form['date_to'])?>" placeholder="гггг-мм-дд"><br>
                                <span class="input-group-addon"><i class="glyphicon glyphicon-calendar"></i></span>
                            </div>
                        </div>
                        <div class="ml15 flex-1">
                            <br>
                            <a id="ext-config-btn" class="btn btn-default pull-right" style="margin-top:4px;">Расширенные настройки..</a>
                        </div>
                    </div>

                    <div id="ext-config" class="<?=($form['use_limits'] || $form['use_sort'] || $form['use_sql_append_select'] || $form['use_sql_append_update']) ? '' : 'hidden'?>">
                        <hr>

                        <div class="mb15">
                            <input type="checkbox" name="form[use_limits]" value="1" <?=($form['use_limits']?'checked':'')?>> Использовать лимиты
                        </div>

                        <div class="flex mb15">
                            <div class="w250">
                                Начать с:<br>
                                <input type="text" name="form[offset]" class="w100p" value="<?=intval($form['offset'])?>" placeholder="0"><br>
                            </div>
                            <div class="ml15 w250">
                                Кол-во:<br>
                                <input type="text" name="form[limit]" class="w100p" value="<?=intval($form['limit'])?>" placeholder="0"><br>
                            </div>
                        </div>

                        <small>** Если не установлены лимиты изменения будет применены ко всем постам</small>

                        <hr>

                        <div class="mb15">
                            <input type="checkbox" name="form[use_sort]" value="1" <?=($form['use_sort']?'checked':'')?>> Использовать сортировку
                        </div>

                        <div class="mb15">
                            <div class="w250">
                                <select name="form[sort]" class="w100p">
                                    <option value="id_asc" <?=($form['sort'] == 'id_asc' ? 'selected="selected"' : '')?>>ID, 0&#11166;9</sort>
                                    <option value="id_desc" <?=($form['sort'] == 'id_desc' ? 'selected="selected"' : '')?>>ID, 9&#11166;0</sort>
                                    <option value="rand" <?=($form['sort'] == 'rand' ? 'selected="selected"' : '')?>>В случайном порядке</sort>
                                </select>

                            </div>
                        </div>

                        <hr>

                        <div class="mb15">
                            <input type="checkbox" name="form[use_sql_append_select]" value="1" <?=($form['use_sql_append_select']?'checked':'')?>> Добавить SQL к выборке постов
                        </div>

                        <div class="mb15">
                            <textarea name="form[sql_append_select]" class="w100p"><?=htmlspecialchars($form['sql_append_select'])?></textarea>
                        </div>

                        <hr>

                        <div class="mb15">
                            <input type="checkbox" name="form[use_sql_append_update]" value="1" <?=($form['use_sql_append_update']?'checked':'')?>> Добавить SQL к обновлению постов
                        </div>

                        <div class="row mb15">
                            <div class="col-md-6">
                                В SET секцию запроса
                                <textarea name="form[sql_append_update_set]" class="w100p"><?=htmlspecialchars($form['sql_append_update_set'])?></textarea>
                            </div>
                            <div class="col-md-6">
                                В WHERE секцию запроса
                                <textarea name="form[sql_append_update_where]" class="w100p"><?=htmlspecialchars($form['sql_append_update_where'])?></textarea>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <input type="submit" name="form[submit_exec_sql]" class="btn btn-primary" value="Выполнить">
                    <input type="submit" name="form[submit_show_sql]" class="btn btn-success" value="Показать SQL">

                    <?php if (!is_null($submitExecSqlResult)):?>
                        <?php if ($submitExecSqlResult === true):?>
                            &nbsp; &nbsp; &nbsp;
                            <span class="text-primary autohide"><span class="glyphicon glyphicon-ok"></span> Выполнено!</span>
                        <?php else:?>

                        <div class="mt15 text-danger">
                            <?=htmlspecialchars($submitExecSqlResult);?>
                        </div>
                        <?php endif;?>
                    <?php endif;?>

                    </form>

                    <?php if ($request->form('submit_show_sql')):?>
                        <hr><h4>SQL код выборки постов</h4>
                        <code><?=str_replace("\n",'<br>',htmlspecialchars($sqlSelectionCode))?></code>
                        <small>**С помощью этого кода будут выбраны посты</small>

                        <hr><h4>SQL код обновления поста</h4>
                        <code><?=str_replace("\n",'<br>',htmlspecialchars($sqlUpdateCode))?></code>
                        <small>**Этот код будет применен для каждого выбранного поста</small>
                    <?php endif;?>

                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" ></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>

    <script>
    $(()=>{
        $(".datepicker").datepicker({
                autoclose: true,
                todayHighlight: true
        });

        $('#ext-config-btn').click(e=>{$('#ext-config').toggleClass('hidden')});

        setTimeout(()=>{
            $('.autohide').hide();
        }, 3000);
    });
    </script>

</body>
</html>
