<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CSR Cafe Games</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body { 
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%);
            min-height: 100vh;
            padding: 15px;
            touch-action: manipulation;
        }
        
        .header {
            background: #1A0F0A;
            color: #C9A961;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .game-card {
            background: white;
            border: 3px solid #C9A961;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
            user-select: none;
        }
        
        .game-card:active {
            transform: scale(0.95);
        }
        
        .game-icon {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }
        
        .game-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1A0F0A;
            margin-bottom: 5px;
        }
        
        .game-players {
            color: #666;
            font-size: 0.85rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 20px;
            width: 100%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #C9A961;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #1A0F0A;
            font-size: 1.3rem;
        }
        
        .btn-close {
            background: #C9A961;
            color: #1A0F0A;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .game-board {
            background: #F5F5F0;
            border: 2px solid #1A0F0A;
            border-radius: 8px;
            margin: 15px 0;
            display: grid;
            gap: 2px;
            max-width: 100%;
            aspect-ratio: 1;
        }
        
        .cell {
            background: white;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }
        
        .cell:active {
            background: #f0f0f0;
        }
        
        .game-status {
            text-align: center;
            font-size: 1.1rem;
            font-weight: bold;
            color: #1A0F0A;
            margin: 10px 0;
            padding: 10px;
            background: #F5F5F0;
            border-radius: 6px;
        }
        
        .btn-reset {
            background: #1A0F0A;
            color: #C9A961;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            display: block;
            width: 100%;
            font-size: 1rem;
            margin-top: 10px;
        }

        .btn-reset:active {
            transform: scale(0.98);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1rem;
        }

        .memory-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 15px 0;
        }

        .memory-card {
            aspect-ratio: 1;
            background: #C9A961;
            border: 2px solid #1A0F0A;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            user-select: none;
        }

        .memory-card.flipped {
            background: white;
        }

        .memory-card.matched {
            background: #4CAF50;
            color: white;
        }

        .player-select {
            margin: 10px 0;
            text-align: center;
        }

        .player-select select {
            padding: 8px 15px;
            border: 2px solid #C9A961;
            border-radius: 5px;
            font-size: 1rem;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎮 CSR CAFE GAMES</h1>
        <p style="margin-top: 8px; font-size: 0.85rem;">Tap to Play</p>
    </div>

    <div class="games-grid">
        <div class="game-card" onclick="openGame('tictactoe')">
            <div class="game-icon">❌⭕</div>
            <div class="game-title">TIC TAC TOE</div>
            <div class="game-players">👥 2 Players</div>
        </div>

        <div class="game-card" onclick="openGame('memory')">
            <div class="game-icon">🧠🃏</div>
            <div class="game-title">MEMORY MATCH</div>
            <div class="game-players">👥 2-4 Players</div>
        </div>
    </div>

    <div id="gameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="gameTitle">Game</h2>
                <button class="btn-close" onclick="closeGame()">✕</button>
            </div>
            <div id="gameContainer"></div>
        </div>
    </div>

    <script>
        let currentGame = null;

        function openGame(gameType) {
            const modal = document.getElementById('gameModal');
            const container = document.getElementById('gameContainer');
            const title = document.getElementById('gameTitle');
            
            modal.classList.add('active');
            container.innerHTML = '<div class="loading">Loading...</div>';
            
            setTimeout(() => {
                currentGame = gameType;
                if (gameType === 'tictactoe') {
                    title.textContent = 'TIC TAC TOE';
                    loadTicTacToe();
                } else if (gameType === 'memory') {
                    title.textContent = 'MEMORY MATCH';
                    loadMemory();
                }
            }, 100);
        }

        function closeGame() {
            document.getElementById('gameModal').classList.remove('active');
            currentGame = null;
        }

        // TIC TAC TOE
        function loadTicTacToe() {
            const container = document.getElementById('gameContainer');
            container.innerHTML = `
                <div id="tttBoard" class="game-board" style="grid-template-columns: repeat(3, 1fr);"></div>
                <div class="game-status" id="tttStatus">Player X's Turn</div>
                <button class="btn-reset" onclick="loadTicTacToe()">NEW GAME</button>
            `;
            
            let board = ['', '', '', '', '', '', '', '', ''];
            let player = 'X';
            let active = true;
            
            const boardDiv = document.getElementById('tttBoard');
            
            for (let i = 0; i < 9; i++) {
                const cell = document.createElement('div');
                cell.className = 'cell';
                cell.onclick = () => {
                    if (!active || board[i] !== '') return;
                    
                    board[i] = player;
                    cell.textContent = player;
                    cell.style.color = player === 'X' ? '#C9A961' : '#1A0F0A';
                    
                    if (checkWin()) {
                        document.getElementById('tttStatus').textContent = `Player ${player} Wins! 🎉`;
                        active = false;
                    } else if (!board.includes('')) {
                        document.getElementById('tttStatus').textContent = "It's a Draw! 🤝";
                        active = false;
                    } else {
                        player = player === 'X' ? 'O' : 'X';
                        document.getElementById('tttStatus').textContent = `Player ${player}'s Turn`;
                    }
                };
                boardDiv.appendChild(cell);
            }
            
            function checkWin() {
                const patterns = [
                    [0,1,2], [3,4,5], [6,7,8],
                    [0,3,6], [1,4,7], [2,5,8],
                    [0,4,8], [2,4,6]
                ];
                return patterns.some(p => board[p[0]] && board[p[0]] === board[p[1]] && board[p[0]] === board[p[2]]);
            }
        }

        // MEMORY MATCH
        function loadMemory() {
            const container = document.getElementById('gameContainer');
            container.innerHTML = `
                <div class="player-select">
                    <label style="font-weight: bold;">Players:</label>
                    <select id="memPlayers">
                        <option value="2">2 Players</option>
                        <option value="3">3 Players</option>
                        <option value="4">4 Players</option>
                    </select>
                    <button onclick="startMemory()" style="padding: 8px 15px; background: #C9A961; border: none; border-radius: 5px; font-weight: bold; margin-left: 5px;">START</button>
                </div>
                <div id="memBoard" class="memory-grid"></div>
                <div class="game-status" id="memStatus"></div>
            `;
        }

        function startMemory() {
            const players = parseInt(document.getElementById('memPlayers').value);
            const symbols = ['☕', '🍪', '🥐', '🍰', '🧁', '🍩', '🥧', '🍮'];
            let cards = [...symbols, ...symbols].sort(() => Math.random() - 0.5);
            let flipped = [];
            let matched = [];
            let currentPlayer = 0;
            let scores = Array(players).fill(0);
            
            const board = document.getElementById('memBoard');
            board.innerHTML = '';
            
            cards.forEach((symbol, i) => {
                const card = document.createElement('div');
                card.className = 'memory-card';
                card.textContent = '?';
                card.onclick = () => flip(i);
                board.appendChild(card);
            });
            
            function flip(i) {
                if (flipped.length === 2 || flipped.includes(i) || matched.includes(i)) return;
                
                const cardEls = board.children;
                cardEls[i].textContent = cards[i];
                cardEls[i].classList.add('flipped');
                flipped.push(i);
                
                if (flipped.length === 2) {
                    setTimeout(() => {
                        const [f1, f2] = flipped;
                        if (cards[f1] === cards[f2]) {
                            matched.push(f1, f2);
                            cardEls[f1].classList.add('matched');
                            cardEls[f2].classList.add('matched');
                            scores[currentPlayer]++;
                            if (matched.length === cards.length) {
                                const winner = scores.indexOf(Math.max(...scores)) + 1;
                                setTimeout(() => alert(`Player ${winner} wins! 🎉`), 300);
                            }
                        } else {
                            cardEls[f1].textContent = '?';
                            cardEls[f1].classList.remove('flipped');
                            cardEls[f2].textContent = '?';
                            cardEls[f2].classList.remove('flipped');
                            currentPlayer = (currentPlayer + 1) % players;
                        }
                        flipped = [];
                        updateStatus();
                    }, 800);
                }
            }
            
            function updateStatus() {
                const status = scores.map((s, i) => `P${i+1}: ${s}`).join(' | ');
                document.getElementById('memStatus').textContent = `Player ${currentPlayer+1}'s Turn | ${status}`;
            }
            
            updateStatus();
        }

        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>
</html>