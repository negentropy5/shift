<?php
require_once 'dbconnect.php';
session_start();
$pdo = connect();

function createToken() {
    if(!isset($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
}

function validateToken() {
    if (empty($_SESSION['token']) || $_SESSION['token'] !== filter_input(INPUT_POST, 'token')) {
        exit('Invalid post request');
    }
}

function h($str) {
    return htmlspecialchars($str,ENT_QUOTES,'UTF-8');
}

function mbtrim($str) {
    return preg_replace("/(^\s+)|(\s+$)/u", "", $str);
}

function index_f() {
    $host = $_SERVER['HTTP_HOST'];
    $url = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    header("Location: //$host$url");
    exit;
}

function inspection_insert() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(id) AS num FROM shift");
        $stmt->execute();
        return $stmt->fetch()['num'];
    } catch (\Exception $e) {
        exit('データ取得に失敗しました');
    }   
}

function inspection_add($add_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(id) AS num FROM shiftadd WHERE add_id = ?");
        $stmt->execute([$add_id]);
        return $stmt->fetch()['num'];
    } catch (\Exception $e) {
        exit('データ取得に失敗しました');
    }   
}

createToken();

$err = [];
$add_err = [];

if($_SERVER["REQUEST_METHOD"] === "POST") {
    validateToken();

    // 最初の登録
    if(filter_input(INPUT_POST, "insert_btn")) {
        // バリデーション
        if(!$hdn = h(mb_substr(mbtrim(filter_input(INPUT_POST, 'hdn')), 0, 10))) {
            $err[] = '名前を入力して下さい';
        }
        $pas = h(filter_input(INPUT_POST, "pas"));
        if(!preg_match("/\A[a-z\d]{1,15}+\z/i", $pas)) {
            $err[] = '削除キーを正しく入力して下さい';
        }

        $ip = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
        $date_7 = $_POST["date_7"] . ' ' . $_POST["hour_7"];

        if(strtotime(date("Y-m-d H:i:s")) > strtotime($date_7)) {
            $err[] = 'その時間は過ぎています';
        }
        if(inspection_insert() >= 50) $err[] = 'これ以上登録できません';

        if(count($err) === 0) {
            $sql = "INSERT INTO shift (hdn, pas, ip, date_7) VALUES (?,?,?,?)";
            $arr = [];
            $arr[] = $hdn;
            $arr[] = $pas;
            $arr[] = $ip;
            $arr[] = $date_7;
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($arr);
                index_f();
            } catch (\Exception $e) {
                exit($e);
            }
        }
    }

    // 参加者登録
    if(filter_input(INPUT_POST, "add_btn")) {
        // バリデーション
        if(!$add_hdn = h(mb_substr(mbtrim(filter_input(INPUT_POST, 'add_hdn')), 0, 15))) {
            $add_err[] = '名前を入力して下さい';
        }
        $add_pas = h(filter_input(INPUT_POST, 'add_pas'));
        if(!preg_match("/\A[a-z\d]{1,15}+\z/i", $add_pas)) {
            $add_err[] = '削除キーを正しく入力して下さい';
        }
        
        $add_id = filter_input(INPUT_POST, 'add_id');
        $ip = gethostbyaddr($_SERVER["REMOTE_ADDR"]);

        if(inspection_add($add_id) >= 10) $add_err[] = '満員です!(10人まで)';
        
        if(count($add_err) === 0) {
            $sql = "INSERT INTO shiftadd (add_hdn, add_pas, ip, add_id) VALUES (?,?,?,?)";
            $arr = [];
            $arr[] = $add_hdn;
            $arr[] = $add_pas;
            $arr[] = $ip;
            $arr[] = $add_id;
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($arr);
                index_f();
            } catch (\Exception $e) {
                exit('入力に失敗しました');
            }
        }
    }

    // 登録を削除
    if(filter_input(INPUT_POST, "del_btn")) {
        // 管理者クリアー
        if(h($_POST['del_key']) === 'adminroot1234') {
            $sql = 'DELETE FROM shift WHERE id = ?';
            $arr = [];
            $arr[] = $_POST['del_id'];
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($arr);
                index_f();
            } catch (\Exception $e) {
                exit('削除に失敗しました');
            }
        } 

        $sql = 'DELETE FROM shift WHERE id = ? AND pas = ?';
        $arr = [];
        $arr[] = $_POST['del_id'];
        $arr[] = h($_POST['del_key']);
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($arr);
            $count = $stmt->rowCount();
            if((int)$count === 0) {
                $err[] = '削除キーが一致しません';
            } else {
                index_f();
            }
        } catch (\Exception $e) {
            exit('削除に失敗しました');
        }
    }

    // 参加者を削除
    if(filter_input(INPUT_POST, "individual_del_btn")) {
        // 管理者クリアー
        if($_POST['individual_del_key'] === 'adminroot1234') {
            $sql = 'DELETE FROM shiftadd WHERE id = ?';
            $arr = [];
            $arr[] = $_POST['individual_del_id'];
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($arr);
                index_f();
            } catch (\Exception $e) {
                exit('削除に失敗しました');
            }
        } 

        $sql = 'DELETE FROM shiftadd WHERE id = ? AND add_pas = ?';
        $arr = [];
        $arr[] = $_POST['individual_del_id'];
        $arr[] = h($_POST['individual_del_key']);
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($arr);
            $count = $stmt->rowCount();
            if((int)$count === 0) {
                $err[] = '削除キーが一致しません';
            } else {
                index_f();
            }
        } catch (\Exception $e) {
            exit('削除に失敗しました');
        }
    }
}

// 日時が過ぎた登録を削除
// $current_d = date("Y-m-d H:i:s", time());
$current_d = date("Y-m-d " . "H:". date("i") - 30 . ":s");
$sql = 'DELETE FROM shift WHERE date_7 < ?';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_d]);
} catch (\Exception $e) {
    exit('通信エラーです');
}

try {
    $stmt = $pdo->query("SELECT id, hdn, date_7, DAY(date_7) AS nichi, HOUR(date_7) AS ji, SUBSTRING(ip, 1, 5) AS ip, created FROM shift ORDER BY date_7");
    $lists = $stmt->fetchAll();
} catch (\Exception $e) {
    exit('データ取得に失敗しました');
}

try {
    $stmt = $pdo->query("SELECT id, add_id, add_hdn, SUBSTRING(ip, 1, 8) AS ip, created FROM shiftadd");
    $add_lists = $stmt->fetchAll();
} catch (\Exception $e) {
    exit('データ取得に失敗しました');
}

// アクセスカウンター
function count_f() {
    $res = explode(',', file('text.txt',FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0]);
    $ip = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
    if($ip !== $res[0]) {
      $res[1] = (int)$res[1] + 1;
      file_put_contents(
        'text.txt',
        $ip . ',' . $res[1] . ',' . date('(Y-m-d H:i:s)', time())
      );
    }
    return $res[1];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="麻雀●場">
    <link href="https://use.fontawesome.com/releases/v6.1.1/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="inyou.ico">
    <title>麻雀●場</title>
</head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-LMCZYDV57H"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-LMCZYDV57H');
</script>
<body>
<main>
<input type="checkbox" id="modal">
    <label class="modal" for="modal">
        <ul>
            <li>
                <label for="modal"><i class="fa-solid fa-xmark"></i></label>
            <li>
                <i class="fa-regular fa-circle-user"></i>
                <a href="../ball">野球○場＿登録</a>
            </li>
            <li>
                <i class="fa-regular fa-circle-user"></i>
                <a href="https://chat.luvul.net/ChatRoom?room_id=342626">野球○場＿会場</a>
            </li>
            <li>
                <i class="fa-regular fa-circle-user"></i>
                <a href="https://chat.luvul.net/ChatRoom?room_id=377737">オセロ○場部屋</a>
            </li>
        </ul>
    </label>

    <h1>
        麻雀〇場シフト表
        <label class="menu" for="modal"><i class="fa-solid fa-bars"></i></label>
        <div class="count"><?= count_f() ?></div>
    </h1>
    <?php foreach($err as $er): ?>
        <div style="color:#4285f4"><?= $er ?></div>
    <?php endforeach ?>

    <form class="register_form" method="post">
        <div>
            <input name="hdn" type="text" placeholder="名前(10文字以内)">
            <select name="date_7" id="date_7"></select>
            <select name="hour_7" id="hour_7">
                <option value="09:00:00">09時</option>
                <option value="10:00:00">10時</option>
                <option value="11:00:00">11時</option>
                <option value="12:00:00">12時</option>
                <option value="13:00:00">13時</option>
                <option value="14:00:00">14時</option>
                <option value="15:00:00">15時</option>
                <option value="16:00:00">16時</option>
                <option value="17:00:00">17時</option>
                <option value="18:00:00">18時</option>
                <option value="19:00:00" selected>19時</option>
                <option value="20:00:00">20時</option>
                <option value="21:00:00">21時</option>
                <option value="22:00:00">22時</option>
                <option value="23:00:00">23時</option>
            </select>
        </div>
        <div>
            <input name="pas" type="text" placeholder="削除キー(半角英数)">
            <input type="submit" name="insert_btn" value="登録">
        </div>
        <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    </form>

    <h2>参加者予定一覧</h2>

    <?php foreach($add_err as $err): ?>
        <div style="color:#4285f4"><?= $err ?></div>
    <?php endforeach ?>
    
    <?php foreach($lists as $list): ?>
    <section>
        <div class="x_btn">
            <div title="<?= $list["created"] ?>">
                <?= $list["hdn"] .'(<span class="ip">' . $list["ip"] . '..</span>)' .  $list["nichi"] . '日' . $list["ji"] . '時' ?>
            </div>
            <div class="del_btn"><i class="fa-regular fa-rectangle-xmark"></i></div>
        </div>
        
        <form class="del_form" method="post">
            <input type="hidden" name="del_key">
            <input type="hidden" name="del_id" value="<?= $list["id"] ?>">
            <input type="hidden" name="del_btn" value="action">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
        </form>

        <?php foreach($add_lists as $add_list): ?>
            <?php if($list["id"] === $add_list["add_id"]): ?>
                <div class="x_btn under">
                    <div title="<?= $add_list["created"] ?>">
                        <?= $add_list["add_hdn"] . '(<span class="ip">' . $add_list["ip"] . '..</span>)' ?>
                    </div>
                    <div class="individual_del_btn"><i class="fa-regular fa-rectangle-xmark"></i></div>
                </div>
                <form class="individual_del_form" method="post">
                    <input type="hidden" name="individual_del_key">
                    <input type="hidden" name="individual_del_id" value="<?= $add_list["id"] ?>">
                    <input type="hidden" name="individual_del_btn" value="action">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                </form>
            <?php endif ?>
        <?php endforeach ?>

        <form class="participant" method="post">
            <input type="text" name="add_hdn" placeholder="名前">
            <input type="text" name="add_pas" placeholder="削除キー(半角英数)">
            <input type="hidden" name="add_id" value='<?= $list["id"] ?>'>
            <input type="submit" name="add_btn" value="参加">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
        </form>
    </section>
    <?php endforeach ?>
</main>

<script>
    const del_btns = document.querySelectorAll('.del_btn');
    del_btns.forEach((del_btn, index) => {
        del_btn.addEventListener('click',()=>{
            const del_key = prompt('削除キーを入力して下さい');
            if(!del_key) return;
            document.querySelectorAll('input[name=del_key]')[index].value = del_key;
            document.querySelectorAll('.del_form')[index].submit();
        });
    });

    const individual_del_btns = document.querySelectorAll('.individual_del_btn');
    individual_del_btns.forEach((del_btn, index) => {
        del_btn.addEventListener('click',()=>{
            const del_key = prompt('削除キーを入力して下さい');
            if(!del_key) return;
            document.querySelectorAll('input[name=individual_del_key]')[index].value = del_key;
            document.querySelectorAll('.individual_del_form')[index].submit();
        });           
    });

    const arr = ['日','月','火','水','木','金','土']
    const today = new Date();
    let year = today.getFullYear();
    let month = today.getMonth();
    let date = today.getDate();
    for(let i = 0; i < 7; i++) {
        const d  = new Date(year, month, date + i).getDate();
        const da = new Date(year, month, date + i).getDay();
        const option = document.createElement('option');
        option.value = `${year}-${month + 1}-${date + i}`;
        option.textContent = `${d}日(${arr[da]})`;
        date_7.appendChild(option);
    }
</script>
</body>
</html>