<?php
session_start();
require_once 'db.php';

// ── CSRF token ──────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── State variables ─────────────────────────────────────────────────────────
$isLoggedIn = !empty($_SESSION['admin_logged_in']);
$flash      = '';
$flashType  = 'success'; // 'success' | 'error'

// ── Handle GET: logout ───────────────────────────────────────────────────────
if (!empty($_GET['action']) && $_GET['action'] === 'logout') {
    session_regenerate_id(true);
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isLoggedIn) {
        // Login attempt
        $password = $_POST['password'] ?? '';
        try {
            $storedHash = getSetting('admin_password');
            if ($storedHash && password_verify($password, $storedHash)) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            } else {
                $flash     = '密碼錯誤，請重試';
                $flashType = 'error';
            }
        } catch (Exception $e) {
            $flash     = '系統錯誤，請重試';
            $flashType = 'error';
        }

    } else {
        // Verify CSRF
        $csrfInput = $_POST['csrf_token'] ?? '';
        if ($csrfInput !== $_SESSION['csrf_token']) {
            $flash     = '請求驗證失敗，請重新操作';
            $flashType = 'error';
        } else {
            $action = $_POST['action'] ?? '';
            try {
                $pdo = getDB();
                switch ($action) {
                    case 'lock':
                        setSetting('platform_locked', '1');
                        $flash = '✅ 平台已鎖定';
                        break;

                    case 'unlock':
                        setSetting('platform_locked', '0');
                        $flash = '✅ 平台已解鎖';
                        break;

                    case 'clear':
                        $pdo->exec("DELETE FROM `rankings`");
                        $flash = '✅ 龍虎榜已清除';
                        break;

                    case 'delete':
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id > 0) {
                            $stmt = $pdo->prepare("DELETE FROM `rankings` WHERE `id` = ?");
                            $stmt->execute([$id]);
                            $flash = '✅ 記錄已刪除';
                        } else {
                            $flash     = '無效的記錄 ID';
                            $flashType = 'error';
                        }
                        break;

                    case 'save_edit':
                        $id    = (int)($_POST['id'] ?? 0);
                        $name  = trim(mb_substr($_POST['name']  ?? '', 0, 100));
                        $moves = (int)($_POST['moves'] ?? 0);
                        if ($id > 0 && $name !== '' && $moves >= 0) {
                            $stmt = $pdo->prepare(
                                "UPDATE `rankings` SET `name` = ?, `moves` = ? WHERE `id` = ?"
                            );
                            $stmt->execute([$name, $moves, $id]);
                            $flash = '✅ 記錄已更新';
                        } else {
                            $flash     = '資料無效，請重試';
                            $flashType = 'error';
                        }
                        break;

                    case 'change_password':
                        $current = $_POST['current_password']  ?? '';
                        $new1    = $_POST['new_password']       ?? '';
                        $new2    = $_POST['confirm_password']   ?? '';
                        $stored  = getSetting('admin_password');
                        if (!$stored || !password_verify($current, $stored)) {
                            $flash     = '當前密碼錯誤';
                            $flashType = 'error';
                        } elseif ($new1 !== $new2) {
                            $flash     = '新密碼與確認密碼不一致';
                            $flashType = 'error';
                        } elseif (mb_strlen($new1) < 4) {
                            $flash     = '新密碼太短（最少 4 個字符）';
                            $flashType = 'error';
                        } else {
                            setSetting('admin_password', password_hash($new1, PASSWORD_DEFAULT));
                            $flash = '✅ 密碼已更新';
                        }
                        break;
                }
            } catch (Exception $e) {
                $flash     = '操作失敗，請重試';
                $flashType = 'error';
            }
            // PRG redirect
            $qs = '?msg=' . urlencode($flash) . '&type=' . urlencode($flashType);
            header('Location: admin.php' . $qs);
            exit;
        }
    }
}

// ── Pick up flash message from redirect ──────────────────────────────────────
if ($flash === '' && !empty($_GET['msg'])) {
    $flash     = $_GET['msg'];
    $flashType = $_GET['type'] ?? 'success';
}

// ── Data for dashboard ───────────────────────────────────────────────────────
$rankings       = [];
$platformLocked = true;
if ($isLoggedIn) {
    try {
        $pdo            = getDB();
        $rankings       = $pdo->query(
            "SELECT `id`, `name`, `moves`, `created_at` FROM `rankings` ORDER BY `moves` DESC"
        )->fetchAll();
        $platformLocked = getSetting('platform_locked') !== '0';
    } catch (Exception $e) {
        // Leave $rankings empty; will show notice in template
    }
}

// Refresh CSRF for template
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理員面板 – 駿馬出征</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Noto Sans TC', Arial, sans-serif;
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #2c3e50;
        }

        h1 {
            text-align: center;
            font-size: 2.5em;
            margin: 10px 0 20px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.15);
        }

        /* ── Flash message ── */
        .flash {
            max-width: 760px;
            margin: 0 auto 18px;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 1em;
            font-weight: bold;
            text-align: center;
            animation: fadeIn 0.4s ease;
        }
        .flash.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .flash.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── Card ── */
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            padding: 28px 32px;
            max-width: 760px;
            margin: 0 auto 24px;
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 1.5em;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-block;
            padding: 10px 22px;
            font-size: 0.95em;
            font-weight: bold;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .btn:hover { filter: brightness(1.1); transform: scale(1.03); }
        .btn-green  { background: #27ae60; color: #fff; }
        .btn-red    { background: #e74c3c; color: #fff; }
        .btn-dark   { background: #2c3e50; color: #fff; }
        .btn-yellow { background: #f39c12; color: #fff; }
        .btn-gray   { background: #95a5a6; color: #fff; }
        .btn-sm     { padding: 6px 14px; font-size: 0.85em; }

        /* ── Login form ── */
        .login-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 55vh;
        }

        .login-wrap .card {
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-wrap h2 { border: none; padding: 0; margin-bottom: 22px; font-size: 1.8em; }

        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 0.95em;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            font-size: 1em;
            border: 2px solid #ccc;
            border-radius: 8px;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #27ae60;
        }

        /* ── Status badge ── */
        .badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .badge-red   { background: #fde8e8; color: #c0392b; }
        .badge-green { background: #e8f8ef; color: #1e8449; }

        /* ── Rankings table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }
        th, td {
            padding: 11px 14px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        th { background: #2c3e50; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover           { background: #eef5fb; }

        /* ── Inline edit modal overlay ── */
        #editOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 800;
        }

        #editModal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.35);
            padding: 32px 36px;
            z-index: 900;
            min-width: 340px;
            text-align: left;
            animation: fadeIn 0.3s ease;
        }

        #editModal h3 {
            margin: 0 0 20px;
            font-size: 1.3em;
            text-align: center;
        }

        #editModal .btn-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 18px;
        }

        /* ── Credit footer ── */
        .credit-footer {
            max-width: 760px;
            margin: 10px auto 0;
            padding: 14px 20px;
            text-align: center;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #ccc;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @media (max-width: 600px) {
            .card { padding: 18px; }
            h1 { font-size: 1.8em; }
        }
    </style>
</head>
<body>

<h1>🛡️ 管理員面板</h1>

<?php if ($flash !== ''): ?>
<div class="flash <?= htmlspecialchars($flashType) ?>">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php if (!$isLoggedIn): ?>
<!-- ════════════════════════ LOGIN ════════════════════════ -->
<div class="login-wrap">
    <div class="card">
        <h2>🔐 管理員登入</h2>
        <form method="POST" action="admin.php">
            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" id="password" name="password" placeholder="輸入管理員密碼" autofocus required>
            </div>
            <button type="submit" class="btn btn-dark" style="width:100%;padding:12px;">登入</button>
        </form>
        <p style="margin-top:16px;font-size:0.85em;color:#888;">
            <a href="index.php" style="color:#2980b9;">← 返回遊戲主頁</a>
        </p>
    </div>
</div>

<?php else: ?>
<!-- ════════════════════════ DASHBOARD ════════════════════════ -->

<!-- ── Platform status ── -->
<div class="card">
    <h2>🔒 平台狀態</h2>
    <p>
        目前狀態：
        <?php if ($platformLocked): ?>
            <span class="badge badge-red">🔒 已鎖定（未解鎖）</span>
        <?php else: ?>
            <span class="badge badge-green">🔓 已解鎖（運行中）</span>
        <?php endif; ?>
    </p>
    <form method="POST" action="admin.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <?php if ($platformLocked): ?>
            <input type="hidden" name="action" value="unlock">
            <button type="submit" class="btn btn-green">🔓 解鎖平台</button>
        <?php else: ?>
            <input type="hidden" name="action" value="lock">
            <button type="submit" class="btn btn-red">🔒 鎖定平台</button>
        <?php endif; ?>
    </form>
    &nbsp;
    <a href="index.php" class="btn btn-dark">← 返回遊戲主頁</a>
    &nbsp;
    <a href="admin.php?action=logout" class="btn btn-gray">登出</a>
</div>

<!-- ── Rankings management ── -->
<div class="card">
    <h2>🐉 龍虎榜管理</h2>

    <?php if (empty($rankings)): ?>
        <p style="color:#888;">龍虎榜目前沒有記錄。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>排名</th>
                    <th>名字</th>
                    <th>移動次數</th>
                    <th>時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankings as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= (int)$row['moves'] ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <button class="btn btn-yellow btn-sm"
                            onclick="openEdit(
                                <?= (int)$row['id'] ?>,
                                <?= json_encode($row['name']) ?>,
                                <?= (int)$row['moves'] ?>
                            )">編輯</button>

                        <form method="POST" action="admin.php" style="display:inline;"
                              onsubmit="return confirm('確定刪除此記錄？');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action"     value="delete">
                            <input type="hidden" name="id"         value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-red btn-sm">刪除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top:18px;">
        <form method="POST" action="admin.php" style="display:inline;"
              onsubmit="return confirm('確定清除龍虎榜所有記錄？此操作不可復原！');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action"     value="clear">
            <button type="submit" class="btn btn-red">🗑️ 清除所有記錄</button>
        </form>
    </div>
</div>

<!-- ── Change password ── -->
<div class="card">
    <h2>🔑 修改密碼</h2>
    <form method="POST" action="admin.php" style="max-width:400px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action"     value="change_password">
        <div class="form-group">
            <label>目前密碼</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>新密碼（最少 4 個字符）</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>確認新密碼</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-dark">更新密碼</button>
    </form>
</div>

<!-- ── Inline edit modal ── -->
<div id="editOverlay" onclick="closeEdit()"></div>
<div id="editModal">
    <h3>✏️ 編輯記錄</h3>
    <form method="POST" action="admin.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action"     value="save_edit">
        <input type="hidden" name="id"         id="editId">
        <div class="form-group">
            <label>玩家名字</label>
            <input type="text"   name="name"  id="editName"  maxlength="100" required>
        </div>
        <div class="form-group">
            <label>移動次數</label>
            <input type="number" name="moves" id="editMoves" min="0" required>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-green">保存</button>
            <button type="button" class="btn btn-gray"  onclick="closeEdit()">取消</button>
        </div>
    </form>
</div>

<script>
    function openEdit(id, name, moves) {
        document.getElementById('editId').value    = id;
        document.getElementById('editName').value  = name;
        document.getElementById('editMoves').value = moves;
        document.getElementById('editOverlay').style.display = 'block';
        document.getElementById('editModal').style.display   = 'block';
    }
    function closeEdit() {
        document.getElementById('editOverlay').style.display = 'none';
        document.getElementById('editModal').style.display   = 'none';
    }
</script>

<?php endif; ?>

<footer class="credit-footer">
    平台由朱智羲同學於2025年製作 &nbsp;|&nbsp; 由黃子謙同學於2026年作修訂 &nbsp;|&nbsp; 並借用給中華基督教會基元中學使用
</footer>

</body>
</html>
