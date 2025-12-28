// ========================================
// TIC TAC TOE GAME
// ========================================
let tttBoard = ['', '', '', '', '', '', '', '', ''];
let tttCurrentPlayer = 'X';
let tttGameActive = true;

function initTicTacToeGame() {
    const canvas = document.getElementById('tttBoard');
    const ctx = canvas.getContext('2d');
    
    tttBoard = ['', '', '', '', '', '', '', '', ''];
    tttCurrentPlayer = 'X';
    tttGameActive = true;
    
    drawTTTBoard(ctx, canvas);
    
    canvas.addEventListener('click', function(e) {
        if (!tttGameActive) return;
        
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const cellSize = canvas.width / 3;
        const col = Math.floor(x / cellSize);
        const row = Math.floor(y / cellSize);
        const index = row * 3 + col;
        
        if (tttBoard[index] === '') {
            tttBoard[index] = tttCurrentPlayer;
            drawTTTBoard(ctx, canvas);
            
            if (checkTTTWinner()) {
                document.getElementById('tttStatus').textContent = `Player ${tttCurrentPlayer} Wins! 🎉`;
                tttGameActive = false;
            } else if (!tttBoard.includes('')) {
                document.getElementById('tttStatus').textContent = "It's a Draw!";
                tttGameActive = false;
            } else {
                tttCurrentPlayer = tttCurrentPlayer === 'X' ? 'O' : 'X';
                document.getElementById('tttStatus').textContent = `Player ${tttCurrentPlayer}'s Turn`;
            }
        }
    });
}

function drawTTTBoard(ctx, canvas) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const cellSize = canvas.width / 3;
    
    ctx.strokeStyle = '#1A0F0A';
    ctx.lineWidth = 3;
    for (let i = 1; i < 3; i++) {
        ctx.beginPath();
        ctx.moveTo(i * cellSize, 0);
        ctx.lineTo(i * cellSize, canvas.height);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(0, i * cellSize);
        ctx.lineTo(canvas.width, i * cellSize);
        ctx.stroke();
    }
    
    ctx.font = 'bold 100px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    
    tttBoard.forEach((cell, index) => {
        const row = Math.floor(index / 3);
        const col = index % 3;
        const x = col * cellSize + cellSize / 2;
        const y = row * cellSize + cellSize / 2;
        
        if (cell === 'X') {
            ctx.fillStyle = '#C9A961';
            ctx.fillText('X', x, y);
        } else if (cell === 'O') {
            ctx.fillStyle = '#1A0F0A';
            ctx.fillText('O', x, y);
        }
    });
}

function checkTTTWinner() {
    const winPatterns = [
        [0, 1, 2], [3, 4, 5], [6, 7, 8],
        [0, 3, 6], [1, 4, 7], [2, 5, 8],
        [0, 4, 8], [2, 4, 6]
    ];
    
    return winPatterns.some(pattern => {
        const [a, b, c] = pattern;
        return tttBoard[a] && tttBoard[a] === tttBoard[b] && tttBoard[a] === tttBoard[c];
    });
}

function resetTTT() {
    initTicTacToeGame();
    document.getElementById('tttStatus').textContent = "Player X's Turn";
}

// ========================================
// CONNECT FOUR GAME
// ========================================
let c4Board = Array(6).fill().map(() => Array(7).fill(0));
let c4CurrentPlayer = 1;
let c4GameActive = true;

function initConnect4Game() {
    const canvas = document.getElementById('c4Board');
    const ctx = canvas.getContext('2d');
    
    c4Board = Array(6).fill().map(() => Array(7).fill(0));
    c4CurrentPlayer = 1;
    c4GameActive = true;
    
    drawC4Board(ctx, canvas);
    
    canvas.addEventListener('click', function(e) {
        if (!c4GameActive) return;
        
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const cellSize = canvas.width / 7;
        const col = Math.floor(x / cellSize);
        
        for (let row = 5; row >= 0; row--) {
            if (c4Board[row][col] === 0) {
                c4Board[row][col] = c4CurrentPlayer;
                drawC4Board(ctx, canvas);
                
                if (checkC4Winner(row, col)) {
                    const color = c4CurrentPlayer === 1 ? 'Red' : 'Yellow';
                    document.getElementById('c4Status').textContent = `${color} Wins! 🎉`;
                    c4GameActive = false;
                } else if (c4Board.every(row => row.every(cell => cell !== 0))) {
                    document.getElementById('c4Status').textContent = "It's a Draw!";
                    c4GameActive = false;
                } else {
                    c4CurrentPlayer = c4CurrentPlayer === 1 ? 2 : 1;
                    const nextColor = c4CurrentPlayer === 1 ? 'Red' : 'Yellow';
                    document.getElementById('c4Status').textContent = `${nextColor}'s Turn`;
                }
                break;
            }
        }
    });
}

function drawC4Board(ctx, canvas) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const cellSize = canvas.width / 7;
    const radius = cellSize / 2 - 10;
    
    ctx.fillStyle = '#0066cc';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    for (let row = 0; row < 6; row++) {
        for (let col = 0; col < 7; col++) {
            const x = col * cellSize + cellSize / 2;
            const y = row * cellSize + cellSize / 2;
            
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            
            if (c4Board[row][col] === 0) {
                ctx.fillStyle = '#ffffff';
            } else if (c4Board[row][col] === 1) {
                ctx.fillStyle = '#ff0000';
            } else {
                ctx.fillStyle = '#ffff00';
            }
            ctx.fill();
            ctx.strokeStyle = '#1A0F0A';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
    }
}

function checkC4Winner(row, col) {
    const player = c4Board[row][col];
    const directions = [
        [[0, 1], [0, -1]],
        [[1, 0], [-1, 0]],
        [[1, 1], [-1, -1]],
        [[1, -1], [-1, 1]]
    ];
    
    return directions.some(([dir1, dir2]) => {
        let count = 1;
        
        for (let [dr, dc] of [dir1, dir2]) {
            let r = row + dr;
            let c = col + dc;
            while (r >= 0 && r < 6 && c >= 0 && c < 7 && c4Board[r][c] === player) {
                count++;
                r += dr;
                c += dc;
            }
        }
        
        return count >= 4;
    });
}

function resetC4() {
    initConnect4Game();
    document.getElementById('c4Status').textContent = "Red's Turn";
}

// ========================================
// CHECKERS GAME
// ========================================
let checkersBoard = Array(8).fill().map(() => Array(8).fill(0));
let checkersCurrentPlayer = 1;
let checkersSelectedPiece = null;
let checkersGameActive = true;

function initCheckersGame() {
    const canvas = document.getElementById('checkersBoard');
    const ctx = canvas.getContext('2d');
    
    checkersBoard = Array(8).fill().map(() => Array(8).fill(0));
    checkersCurrentPlayer = 1;
    checkersSelectedPiece = null;
    checkersGameActive = true;
    
    for (let row = 0; row < 3; row++) {
        for (let col = 0; col < 8; col++) {
            if ((row + col) % 2 === 1) {
                checkersBoard[row][col] = 2;
            }
        }
    }
    for (let row = 5; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            if ((row + col) % 2 === 1) {
                checkersBoard[row][col] = 1;
            }
        }
    }
    
    drawCheckersBoard(ctx, canvas);
    
    canvas.addEventListener('click', function(e) {
        if (!checkersGameActive) return;
        
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const cellSize = canvas.width / 8;
        const col = Math.floor(x / cellSize);
        const row = Math.floor(y / cellSize);
        
        handleCheckersClick(row, col, ctx, canvas);
    });
}

function drawCheckersBoard(ctx, canvas) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const cellSize = canvas.width / 8;
    
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            ctx.fillStyle = (row + col) % 2 === 0 ? '#F5F5F0' : '#8B4513';
            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
        }
    }
    
    const radius = cellSize / 2 - 10;
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            if (checkersBoard[row][col] !== 0) {
                const x = col * cellSize + cellSize / 2;
                const y = row * cellSize + cellSize / 2;
                
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.fillStyle = checkersBoard[row][col] === 1 ? '#ff0000' : '#000000';
                ctx.fill();
                ctx.strokeStyle = '#C9A961';
                ctx.lineWidth = 3;
                ctx.stroke();
                
                if (Math.abs(checkersBoard[row][col]) === 3) {
                    ctx.fillStyle = '#FFD700';
                    ctx.font = 'bold 30px Arial';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('♔', x, y);
                }
            }
        }
    }
    
    if (checkersSelectedPiece) {
        const [sRow, sCol] = checkersSelectedPiece;
        ctx.strokeStyle = '#00ff00';
        ctx.lineWidth = 5;
        ctx.strokeRect(sCol * cellSize, sRow * cellSize, cellSize, cellSize);
    }
}

function handleCheckersClick(row, col, ctx, canvas) {
    const piece = checkersBoard[row][col];
    
    if (checkersSelectedPiece) {
        const [sRow, sCol] = checkersSelectedPiece;
        
        if (Math.abs(row - sRow) === 1 && Math.abs(col - sCol) === 1 && piece === 0) {
            checkersBoard[row][col] = checkersBoard[sRow][sCol];
            checkersBoard[sRow][sCol] = 0;
            
            if (row === 0 && checkersBoard[row][col] === 1) checkersBoard[row][col] = 3;
            if (row === 7 && checkersBoard[row][col] === 2) checkersBoard[row][col] = -3;
            
            checkersCurrentPlayer = checkersCurrentPlayer === 1 ? 2 : 1;
            checkersSelectedPiece = null;
        } else if (Math.abs(row - sRow) === 2 && Math.abs(col - sCol) === 2 && piece === 0) {
            const midRow = (row + sRow) / 2;
            const midCol = (col + sCol) / 2;
            const midPiece = checkersBoard[midRow][midCol];
            
            if (midPiece !== 0 && Math.sign(midPiece) !== Math.sign(checkersBoard[sRow][sCol])) {
                checkersBoard[row][col] = checkersBoard[sRow][sCol];
                checkersBoard[sRow][sCol] = 0;
                checkersBoard[midRow][midCol] = 0;
                
                if (row === 0 && checkersBoard[row][col] === 1) checkersBoard[row][col] = 3;
                if (row === 7 && checkersBoard[row][col] === 2) checkersBoard[row][col] = -3;
                
                checkersCurrentPlayer = checkersCurrentPlayer === 1 ? 2 : 1;
                checkersSelectedPiece = null;
            }
        } else {
            checkersSelectedPiece = null;
        }
        
        drawCheckersBoard(ctx, canvas);
        document.getElementById('checkersTurn').textContent = 
            checkersCurrentPlayer === 1 ? "Red's Turn" : "Black's Turn";
    } else if (piece !== 0 && Math.sign(piece) === checkersCurrentPlayer) {
        checkersSelectedPiece = [row, col];
        drawCheckersBoard(ctx, canvas);
    }
}

function resetCheckers() {
    initCheckersGame();
    document.getElementById('checkersTurn').textContent = "Red's Turn";
}

// ========================================
// CHESS GAME (Simplified)
// ========================================
let chessBoard = [];
let chessCurrentPlayer = 'white';
let chessSelectedPiece = null;

function initChessGame() {
    const canvas = document.getElementById('chessBoard');
    const ctx = canvas.getContext('2d');
    
    chessBoard = [
        ['r', 'n', 'b', 'q', 'k', 'b', 'n', 'r'],
        ['p', 'p', 'p', 'p', 'p', 'p', 'p', 'p'],
        [0, 0, 0, 0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0],
        ['P', 'P', 'P', 'P', 'P', 'P', 'P', 'P'],
        ['R', 'N', 'B', 'Q', 'K', 'B', 'N', 'R']
    ];
    
    chessCurrentPlayer = 'white';
    chessSelectedPiece = null;
    
    drawChessBoard(ctx, canvas);
    
    canvas.addEventListener('click', function(e) {
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const cellSize = canvas.width / 8;
        const col = Math.floor(x / cellSize);
        const row = Math.floor(y / cellSize);
        
        handleChessClick(row, col, ctx, canvas);
    });
}

function drawChessBoard(ctx, canvas) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const cellSize = canvas.width / 8;
    
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            ctx.fillStyle = (row + col) % 2 === 0 ? '#F0D9B5' : '#B58863';
            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
        }
    }
    
    const pieceSymbols = {
        'k': '♚', 'q': '♛', 'r': '♜', 'b': '♝', 'n': '♞', 'p': '♟',
        'K': '♔', 'Q': '♕', 'R': '♖', 'B': '♗', 'N': '♘', 'P': '♙'
    };
    
    ctx.font = 'bold 50px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            const piece = chessBoard[row][col];
            if (piece !== 0) {
                const x = col * cellSize + cellSize / 2;
                const y = row * cellSize + cellSize / 2;
                ctx.fillStyle = piece === piece.toUpperCase() ? '#ffffff' : '#000000';
                ctx.fillText(pieceSymbols[piece], x, y);
            }
        }
    }
    
    if (chessSelectedPiece) {
        const [sRow, sCol] = chessSelectedPiece;
        ctx.strokeStyle = '#00ff00';
        ctx.lineWidth = 5;
        ctx.strokeRect(sCol * cellSize, sRow * cellSize, cellSize, cellSize);
    }
}

function handleChessClick(row, col, ctx, canvas) {
    const piece = chessBoard[row][col];
    
    if (chessSelectedPiece) {
        const [sRow, sCol] = chessSelectedPiece;
        chessBoard[row][col] = chessBoard[sRow][sCol];
        chessBoard[sRow][sCol] = 0;
        
        chessCurrentPlayer = chessCurrentPlayer === 'white' ? 'black' : 'white';
        chessSelectedPiece = null;
        
        drawChessBoard(ctx, canvas);
        document.getElementById('chessTurn').textContent = 
            chessCurrentPlayer === 'white' ? "White's Turn" : "Black's Turn";
    } else if (piece !== 0) {
        const isWhite = piece === piece.toUpperCase();
        if ((chessCurrentPlayer === 'white' && isWhite) || (chessCurrentPlayer === 'black' && !isWhite)) {
            chessSelectedPiece = [row, col];
            drawChessBoard(ctx, canvas);
        }
    }
}

function resetChess() {
    initChessGame();
    document.getElementById('chessTurn').textContent = "White's Turn";
}

// ========================================
// DOTS AND BOXES GAME
// ========================================
let dotsGrid = 6;
let dotsLines = { h: [], v: [] };
let dotsBoxes = [];
let dotsCurrentPlayer = 1;
let dotsScores = [0, 0];

function initDotsGame() {
    const canvas = document.getElementById('dotsBoard');
    const ctx = canvas.getContext('2d');
    
    dotsLines = { 
        h: Array(dotsGrid).fill().map(() => Array(dotsGrid - 1).fill(0)),
        v: Array(dotsGrid - 1).fill().map(() => Array(dotsGrid).fill(0))
    };
    dotsBoxes = Array(dotsGrid - 1).fill().map(() => Array(dotsGrid - 1).fill(0));
    dotsCurrentPlayer = 1;
    dotsScores = [0, 0];
    
    drawDotsBoard(ctx, canvas);
    
    canvas.addEventListener('click', function(e) {
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        handleDotsClick(x, y, ctx, canvas);
    });
}

function drawDotsBoard(ctx, canvas) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const spacing = canvas.width / dotsGrid;
    
    for (let row = 0; row < dotsGrid; row++) {
        for (let col = 0; col < dotsGrid; col++) {
            ctx.beginPath();
            ctx.arc(col * spacing + spacing / 2, row * spacing + spacing / 2, 5, 0, Math.PI * 2);
            ctx.fillStyle = '#1A0F0A';
            ctx.fill();
        }
    }
    
    for (let row = 0; row < dotsGrid; row++) {
        for (let col = 0; col < dotsGrid - 1; col++) {
            if (dotsLines.h[row][col] !== 0) {
                ctx.beginPath();
                ctx.moveTo(col * spacing + spacing / 2, row * spacing + spacing / 2);
                ctx.lineTo((col + 1) * spacing + spacing / 2, row * spacing + spacing / 2);
                ctx.strokeStyle = dotsLines.h[row][col] === 1 ? '#0066cc' : '#ff0000';
                ctx.lineWidth = 4;
                ctx.stroke();
            }
        }
    }
    
    for (let row = 0; row < dotsGrid - 1; row++) {
        for (let col = 0; col < dotsGrid; col++) {
            if (dotsLines.v[row][col] !== 0) {
                ctx.beginPath();
                ctx.moveTo(col * spacing + spacing / 2, row * spacing + spacing / 2);
                ctx.lineTo(col * spacing + spacing / 2, (row + 1) * spacing + spacing / 2);
                ctx.strokeStyle = dotsLines.v[row][col] === 1 ? '#0066cc' : '#ff0000';
                ctx.lineWidth = 4;
                ctx.stroke();
            }
        }
    }
    
    for (let row = 0; row < dotsGrid - 1; row++) {
        for (let col = 0; col < dotsGrid - 1; col++) {
            if (dotsBoxes[row][col] !== 0) {
                ctx.fillStyle = dotsBoxes[row][col] === 1 ? 'rgba(0, 102, 204, 0.3)' : 'rgba(255, 0, 0, 0.3)';
                ctx.fillRect(col * spacing + spacing / 2 + 2, row * spacing + spacing / 2 + 2, spacing - 4, spacing - 4);
            }
        }
    }
}

function handleDotsClick(x, y, ctx, canvas) {
    const spacing = canvas.width / dotsGrid;
    const threshold = 15;
    
    for (let row = 0; row < dotsGrid; row++) {
        for (let col = 0; col < dotsGrid - 1; col++) {
            const lineX1 = col * spacing + spacing / 2;
            const lineX2 = (col + 1) * spacing + spacing / 2;
            const lineY = row * spacing + spacing / 2;
            
            if (Math.abs(y - lineY) < threshold && x > lineX1 && x < lineX2 && dotsLines.h[row][col] === 0) {
                dotsLines.h[row][col] = dotsCurrentPlayer;
                const boxCompleted = checkDotsBoxes();
                if (!boxCompleted) {
                    dotsCurrentPlayer = dotsCurrentPlayer === 1 ? 2 : 1;
                }
                drawDotsBoard(ctx, canvas);
                updateDotsStatus();
                return;
            }
        }
    }
    
    for (let row = 0; row < dotsGrid - 1; row++) {
        for (let col = 0; col < dotsGrid; col++) {
            const lineX = col * spacing + spacing / 2;
            const lineY1 = row * spacing + spacing / 2;
            const lineY2 = (row + 1) * spacing + spacing / 2;
            
            if (Math.abs(x - lineX) < threshold && y > lineY1 && y < lineY2 && dotsLines.v[row][col] === 0) {
                dotsLines.v[row][col] = dotsCurrentPlayer;
                const boxCompleted = checkDotsBoxes();
                if (!boxCompleted) {
                    dotsCurrentPlayer = dotsCurrentPlayer === 1 ? 2 : 1;
                }
                drawDotsBoard(ctx, canvas);
                updateDotsStatus();
                return;
            }
        }
    }
}

function checkDotsBoxes() {
    let boxCompleted = false;
    for (let row = 0; row < dotsGrid - 1; row++) {
        for (let col = 0; col < dotsGrid - 1; col++) {
            if (dotsBoxes[row][col] === 0 &&
                dotsLines.h[row][col] !== 0 &&
                dotsLines.h[row + 1][col] !== 0 &&
                dotsLines.v[row][col] !== 0 &&
                dotsLines.v[row][col + 1] !== 0) {
                dotsBoxes[row][col] = dotsCurrentPlayer;
                dotsScores[dotsCurrentPlayer - 1]++;
                boxCompleted = true;
            }
        }
    }
    return boxCompleted;
}

function updateDotsStatus() {
    const color = dotsCurrentPlayer === 1 ? 'Blue' : 'Red';
    document.getElementById('dotsStatus').textContent = 
        `${color}'s Turn | Blue: ${dotsScores[0]} | Red: ${dotsScores[1]}`;
}

function resetDots() {
    initDotsGame();
    updateDotsStatus();
}

// ========================================
// MEMORY MATCH GAME
// ========================================
let memoryCards = [];
let memoryFlipped = [];
let memoryMatched = [];
let memoryPlayers = 2;
let memoryCurrentPlayer = 0;
let memoryScores = [];

function startMemoryGame() {
    memoryPlayers = parseInt(document.getElementById('memoryPlayers').value);
    memoryScores = Array(memoryPlayers).fill(0);
    memoryCurrentPlayer = 0;
    
    const symbols = ['☕', '🍪', '🥐', '🍰', '🧁', '🍩', '🥧', '🍮'];
    memoryCards = [...symbols, ...symbols].sort(() => Math.random() - 0.5);
    memoryFlipped = [];
    memoryMatched = [];
    
    const board = document.getElementById('memoryBoard');
    board.innerHTML = '';
    
    memoryCards.forEach((symbol, index) => {
        const card = document.createElement('div');
        card.style.cssText = `
            width: 100px; height: 100px; 
            background: #C9A961; 
            border: 3px solid #1A0F0A; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 3rem; 
            cursor: pointer; 
            transition: transform 0.3s;
        `;
        card.textContent = '?';
        card.dataset.index = index;
        card.addEventListener('click', () => flipMemoryCard(index));
        board.appendChild(card);
    });
    
    updateMemoryScores();
}

function flipMemoryCard(index) {
    if (memoryFlipped.length === 2 || memoryFlipped.includes(index) || memoryMatched.includes(index)) {
        return;
    }
    
    const cards = document.getElementById('memoryBoard').children;
    cards[index].textContent = memoryCards[index];
    cards[index].style.background = '#ffffff';
    memoryFlipped.push(index);
    
    if (memoryFlipped.length === 2) {
        setTimeout(() => {
            const [first, second] = memoryFlipped;
            if (memoryCards[first] === memoryCards[second]) {
                memoryMatched.push(first, second);
                memoryScores[memoryCurrentPlayer]++;
                updateMemoryScores();
                
                if (memoryMatched.length === memoryCards.length) {
                    const winner = memoryScores.indexOf(Math.max(...memoryScores)) + 1;
                    alert(`Player ${winner} wins! 🎉`);
                }
            } else {
                cards[first].textContent = '?';
                cards[first].style.background = '#C9A961';
                cards[second].textContent = '?';
                cards[second].style.background = '#C9A961';
                
                memoryCurrentPlayer = (memoryCurrentPlayer + 1) % memoryPlayers;
                updateMemoryScores();
            }
            memoryFlipped = [];
        }, 1000);
    }
}

function updateMemoryScores() {
    const scoresDiv = document.getElementById('memoryScores');
    scoresDiv.innerHTML = memoryScores.map((score, i) => 
        `<span style="color: ${i === memoryCurrentPlayer ? '#C9A961' : '#666'}; font-weight: ${i === memoryCurrentPlayer ? 'bold' : 'normal'};">
            Player ${i + 1}: ${score}
        </span>`
    ).join(' | ');
}