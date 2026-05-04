<?php
require_once 'db.php';

$isLocked = true;
$dbError  = false;
try {
    $isLocked = getSetting('platform_locked') !== '0';
} catch (Exception $e) {
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>駿馬出征</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
    <?php if (!$isLocked && !$dbError): ?>
    <link rel="preload" href="./imagek.jpg" as="image">
    <?php endif; ?>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Noto Sans TC', Arial, sans-serif;
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            animation: backgroundShift 10s ease infinite;
        }

        @keyframes backgroundShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        h1 {
            margin: 20px 0;
            color: #2c3e50;
            font-size: 3.5em;
            text-align: center;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.2);
            animation: fadeIn 1s ease;
        }

        .game-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
            width: 100%;
            max-width: 800px;
        }

        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 50px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .board {
            display: grid;
            grid-template-columns: repeat(6, 80px);
            grid-template-rows: repeat(6, 80px);
            gap: 5px;
            background-color: #2c3e50;
            padding: 15px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            display: none;
        }

        .cell {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0d9b5;
            border: 4px solid #34495e;
            cursor: pointer;
            font-size: 30px;
            font-weight: bold;
            color: #2c3e50;
            transition: all 0.3s ease;
            border-radius: 12px;
            position: relative;
        }

        .cell.visited {
            background-color: #b58863;
            color: #fff;
        }

        .cell.visited::after {
            content: "❌";
            position: absolute;
            font-size: 40px;
            line-height: 1;
            color: #e74c3c;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            transform: translateY(-5px);
        }

        .cell.current {
            border: 6px solid #e74c3c;
            transform: scale(1.15);
            box-shadow: 0 0 25px rgba(231, 76, 60, 0.6);
            z-index: 1;
        }

        .cell.current img {
            width: 80%;
            height: 80%;
            object-fit: contain;
            image-rendering: optimizeSpeed;
            transition: transform 0.3s ease;
        }

        .cell:hover {
            background-color: #d4a373;
            transform: scale(1.08);
        }

        .cell.invalid {
            animation: shake 0.3s ease;
        }

        .button-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .start-button {
            padding: 20px 40px;
            font-size: 25px;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(145deg, #27ae60, #219653);
            border: 4px solid #1b5e20;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .start-button:hover {
            transform: scale(1.1);
            filter: brightness(1.15);
        }

        .move-counter, .timer {
            font-size: 35px;
            font-weight: 600;
            color: #2c3e50;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.2);
            padding: 10px 20px;
            border-radius: 15px;
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            box-shadow: 5px 5px 10px rgba(0,0,0,0.1);
            border: 4px solid #2c3e50;
        }

        .timer {
            color: #e74c3c;
            border-color: #e74c3c;
            animation: pulse 1.2s infinite;
        }

        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            text-align: center;
            font-size: 20px;
            min-width: 500px;
            animation: fadeIn 0.5s ease;
        }

        .modal h2 {
            font-size: 40px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .modal input {
            padding: 10px;
            font-size: 18px;
            width: 250px;
            border: 2px solid #27ae60;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .modal button {
            padding: 15px 30px;
            font-size: 20px;
            color: #fff;
            background: linear-gradient(145deg, #27ae60, #219653);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal button:hover {
            transform: scale(1.1);
            filter: brightness(1.15);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 999;
        }

        #rankingList {
            width: 100%;
            max-width: 600px;
            text-align: center;
            color: #2c3e50;
        }

        #rankingList h2 {
            font-size: 2em;
            margin: 20px 0;
        }

        #rankingList table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        #rankingList th, #rankingList td {
            padding: 15px;
            border: 1px solid #ddd;
        }

        #rankingList th {
            background: #27ae60;
            color: #fff;
        }

        #rankingList tr:nth-child(even) {
            background: #f9f9f9;
        }

        /* Locked / error page */
        .status-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 50vh;
            text-align: center;
            animation: fadeIn 0.8s ease;
        }

        .status-icon {
            font-size: 90px;
            margin-bottom: 20px;
        }

        .status-title {
            font-size: 2.5em;
            font-weight: 700;
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .status-message {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 30px;
        }

        .admin-link {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(145deg, #2c3e50, #34495e);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .admin-link:hover {
            transform: scale(1.05);
            filter: brightness(1.2);
        }

        /* Credit footer */
        .credit-footer {
            margin-top: 40px;
            padding: 14px 20px;
            text-align: center;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #ccc;
            width: 100%;
            max-width: 800px;
        }

        /* Responsive */
        @media (max-width: 600px) {
            h1 { font-size: 2.5em; }
            .board {
                grid-template-columns: repeat(6, 50px);
                grid-template-rows: repeat(6, 50px);
            }
            .cell { width: 50px; height: 50px; font-size: 20px; }
            .cell.visited::after { font-size: 30px; transform: translateY(-3px); }
            .start-button { padding: 15px 30px; font-size: 20px; }
            .move-counter, .timer { font-size: 25px; padding: 8px 15px; }
            .modal { min-width: 90%; padding: 20px; }
            .modal h2 { font-size: 30px; }
            .modal input { width: 80%; }
            .stats-container { gap: 20px; }
            #rankingList table { font-size: 14px; }
        }

        @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pop     { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
        @keyframes pulse   { 0% { transform: scale(1); } 50% { transform: scale(1.08); } 100% { transform: scale(1); } }
        @keyframes shake   { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 50% { transform: translateX(5px); } 75% { transform: translateX(-5px); } }
    </style>
</head>
<body>
    <h1>駿馬出征</h1>

<?php if ($dbError): ?>
    <div class="status-container">
        <div class="status-icon">⚠️</div>
        <div class="status-title">系統暫時無法連線</div>
        <div class="status-message">無法連接到資料庫，請稍後再試</div>
        <a href="admin.php" class="admin-link">🔑 管理員入口</a>
    </div>

<?php elseif ($isLocked): ?>
    <div class="status-container">
        <div class="status-icon">🔒</div>
        <div class="status-title">平台目前未解鎖</div>
        <div class="status-message">平台暫時關閉，請聯絡管理員以解鎖</div>
        <a href="admin.php" class="admin-link">🔑 管理員入口</a>
    </div>

<?php else: ?>
    <div class="button-container">
        <button class="start-button" onclick="showGuide()">開始遊戲</button>
    </div>
    <div id="rankingList"></div>

    <div class="game-container">
        <div class="stats-container">
            <div class="move-counter" id="moveCounter">移動次數: 0</div>
            <div class="timer" id="timer">剩餘時間: 60 秒</div>
        </div>
        <div class="board" id="board"></div>
    </div>

    <!-- 音效元素 -->
    <audio id="moveSound" src="https://freesound.org/data/previews/270/270304_5123851-lq.mp3"></audio>
    <audio id="timeSound" src="https://freesound.org/data/previews/254/254629_4753328-lq.mp3"></audio>
    <audio id="endSound"  src="https://freesound.org/data/previews/171/171671_2438878-lq.mp3"></audio>

    <div class="overlay" id="overlay"></div>
    <div class="modal" id="modal">
        <div class="modal-content">
            <h2>遊戲指南</h2>
            <input type="text" id="playerName" placeholder="輸入玩家名字">
            <p>遊戲規則：</p>
            <ol>
                <li>點擊棋盤上的格子來移動駿馬</li>
                <li>駿馬只能按照「日」字形移動</li>
                <li>目標是在 60 秒內走遍最多格子</li>
                <li>當時間到或無法移動時遊戲結束</li>
            </ol>
            <button onclick="startGame()">確定</button>
        </div>
    </div>

    <div class="overlay" id="endOverlay"></div>
    <div class="modal" id="endModal">
        <div class="modal-content">
            <h2>遊戲結束</h2>
            <p id="endMessage"></p>
            <button onclick="restartGame()">重新開始</button>
            <button onclick="closeEndModal()">關閉</button>
        </div>
    </div>

    <script>
        const boardSize    = 6;
        const boardElement = document.getElementById('board');
        const moveCounter  = document.getElementById('moveCounter');
        const timerElement = document.getElementById('timer');
        const modal        = document.getElementById('modal');
        const overlay      = document.getElementById('overlay');
        const endModal     = document.getElementById('endModal');
        const endOverlay   = document.getElementById('endOverlay');
        const endMessage   = document.getElementById('endMessage');
        const rankingList  = document.getElementById('rankingList');
        let board          = [];
        let knightPosition = null;
        let moveCount      = 0;
        let gameStarted    = false;
        let timeLeft       = 60;
        let timerInterval  = null;
        let playerName     = "玩家";
        const knightImage  = "imagek2.jpg"; // 本地 JPG 文件

        function initializeBoard() {
            boardElement.innerHTML = '';
            for (let row = 0; row < boardSize; row++) {
                board[row] = [];
                for (let col = 0; col < boardSize; col++) {
                    board[row][col] = { visited: false, element: null };
                    const cell = document.createElement('div');
                    cell.classList.add('cell');
                    cell.dataset.row = row;
                    cell.dataset.col = col;
                    cell.addEventListener('click', () => {
                        if (gameStarted) moveKnight(row, col);
                    });
                    boardElement.appendChild(cell);
                    board[row][col].element = cell;
                }
            }
            knightPosition = null;
            moveCount      = 0;
            moveCounter.textContent = '移動次數: 0';
            gameStarted = false;
            displayRankings();
        }

        function showGuide() {
            modal.style.display   = 'block';
            overlay.style.display = 'block';
        }

        function startGame() {
            const nameInput = document.getElementById('playerName');
            playerName = nameInput.value.trim() || "玩家";
            modal.style.display   = 'none';
            overlay.style.display = 'none';
            gameStarted = true;
            boardElement.style.display = 'grid';
            placeKnightRandomly();
            startTimer();
        }

        function placeKnightRandomly() {
            const row = Math.floor(Math.random() * boardSize);
            const col = Math.floor(Math.random() * boardSize);
            moveKnight(row, col);
        }

        function moveKnight(row, col) {
            if (knightPosition === null) {
                knightPosition = { row, col };
                board[row][col].element.classList.add('current');
                board[row][col].element.innerHTML = `<img src="${knightImage}" alt="駿馬" loading="lazy" decoding="async">`;
                board[row][col].element.classList.add('pop');
                moveCount++;
                moveCounter.textContent = `移動次數: ${moveCount}`;
                document.getElementById('moveSound').play();
            } else {
                const currentRow = knightPosition.row;
                const currentCol = knightPosition.col;
                const rowDiff    = Math.abs(row - currentRow);
                const colDiff    = Math.abs(col - currentCol);

                if ((rowDiff === 2 && colDiff === 1) || (rowDiff === 1 && colDiff === 2)) {
                    if (!board[row][col].visited) {
                        const prevCell = board[currentRow][currentCol];
                        prevCell.element.classList.remove('current');
                        prevCell.element.innerHTML = '';
                        prevCell.visited = true;
                        prevCell.element.classList.add('visited');

                        knightPosition = { row, col };
                        board[row][col].element.classList.add('current');
                        board[row][col].element.innerHTML = `<img src="${knightImage}" alt="駿馬" loading="lazy" decoding="async">`;
                        board[row][col].element.classList.add('pop');
                        moveCount++;
                        moveCounter.textContent = `移動次數: ${moveCount}`;
                        document.getElementById('moveSound').play();

                        if (!hasValidMoves(row, col)) {
                            endGame();
                        }
                    } else {
                        board[row][col].element.classList.add('invalid');
                        setTimeout(() => board[row][col].element.classList.remove('invalid'), 300);
                    }
                } else {
                    board[row][col].element.classList.add('invalid');
                    setTimeout(() => board[row][col].element.classList.remove('invalid'), 300);
                }
            }
        }

        function getValidMoves(row, col) {
            const moves = [
                { row: row + 2, col: col + 1 }, { row: row + 2, col: col - 1 },
                { row: row - 2, col: col + 1 }, { row: row - 2, col: col - 1 },
                { row: row + 1, col: col + 2 }, { row: row + 1, col: col - 2 },
                { row: row - 1, col: col + 2 }, { row: row - 1, col: col - 2 },
            ];
            return moves.filter(move =>
                move.row >= 0 && move.row < boardSize &&
                move.col >= 0 && move.col < boardSize &&
                !board[move.row][move.col].visited
            );
        }

        function hasValidMoves(row, col) {
            return getValidMoves(row, col).length > 0;
        }

        function startTimer() {
            timeLeft = 60;
            timerElement.textContent = `剩餘時間: ${timeLeft} 秒`;
            timerInterval = setInterval(() => {
                timeLeft--;
                document.getElementById('timeSound').play();
                timerElement.textContent = `剩餘時間: ${timeLeft} 秒`;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    endGame();
                }
            }, 1000);
        }

        function endGame() {
            document.getElementById('endSound').play();
            gameStarted = false;
            clearInterval(timerInterval);
            const totalCells = boardSize * boardSize;
            endMessage.textContent = `${playerName} 遊戲結束！共移動 ${moveCount} 格（${moveCount}/${totalCells}）`;
            endModal.style.display   = 'block';
            endOverlay.style.display = 'block';
            saveRanking();
            displayRankings();
        }

        // Save score to server
        function saveRanking() {
            fetch('save_score.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ name: playerName, moves: moveCount })
            })
            .then(r => r.json())
            .then(data => { if (data.success) displayRankings(); })
            .catch(() => {});
        }

        // Display top-10 rankings fetched from server
        function displayRankings() {
            fetch('get_rankings.php')
                .then(r => r.json())
                .then(rankings => {
                    rankingList.innerHTML = '<h2>🐉 龍虎榜 🐯</h2>';
                    if (rankings.length === 0) {
                        rankingList.innerHTML += '<p>暫無記錄</p>';
                    } else {
                        const table = document.createElement('table');
                        table.innerHTML = '<tr><th>排名</th><th>名字</th><th>移動次數</th></tr>';
                        rankings.forEach((record, index) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `<td>${index + 1}</td><td>${escapeHtml(record.name)}</td><td>${parseInt(record.moves)}</td>`;
                            table.appendChild(row);
                        });
                        rankingList.appendChild(table);
                    }
                })
                .catch(() => {
                    rankingList.innerHTML = '<h2>🐉 龍虎榜 🐯</h2><p>無法載入排名</p>';
                });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function restartGame() {
            endModal.style.display   = 'none';
            endOverlay.style.display = 'none';
            initializeBoard();
            startGame();
        }

        function closeEndModal() {
            endModal.style.display   = 'none';
            endOverlay.style.display = 'none';
            initializeBoard();
            showGuide();
        }

        initializeBoard();
    </script>
<?php endif; ?>

    <footer class="credit-footer">
        平台由朱智羲同學於2025年製作 &nbsp;|&nbsp; 由黃子謙同學於2026年作修訂 &nbsp;|&nbsp; 並借用給中華基督教會基元中學使用
    </footer>
</body>
</html>
