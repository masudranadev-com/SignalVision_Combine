// Admin Panel JavaScript

const API_URL = window.location.origin;
let currentUser = null;
let trades = [];
let prices = {};
let updateInterval = null;

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    if (token && user) {
        currentUser = JSON.parse(user);
        showDashboard();
    }
});

// Login Handler
function setupLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch(`${API_URL}/api/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.success) {
                    currentUser = data.user;
                    localStorage.setItem('token', data.token);
                    localStorage.setItem('user', JSON.stringify(data.user));
                    showDashboard();
                } else {
                    showError(data.error || 'Invalid credentials');
                }
            } catch (error) {
                showError('Connection error');
            }
        });
    }
}

// Show error message
function showError(message) {
    const errorDiv = document.getElementById('loginError');
    if (errorDiv) {
        errorDiv.innerHTML = `<div class="alert alert-error">${message}</div>`;
    }
}

// Show dashboard
function showDashboard() {
    const loginPage = document.getElementById('loginPage');
    const dashboard = document.getElementById('dashboard');

    if (loginPage) loginPage.style.display = 'none';
    if (dashboard) {
        dashboard.style.display = 'block';
        const userDisplay = document.getElementById('userDisplay');
        if (userDisplay) {
            userDisplay.textContent = currentUser.username;
        }
        loadData();
        updateInterval = setInterval(loadData, 2000); // Update every 2 seconds
    }
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    clearInterval(updateInterval);
    const loginPage = document.getElementById('loginPage');
    const dashboard = document.getElementById('dashboard');
    if (loginPage) loginPage.style.display = 'flex';
    if (dashboard) dashboard.style.display = 'none';
    currentUser = null;
}

// Load data from API
async function loadData() {
    try {
        const [tradesRes, pricesRes, statsRes] = await Promise.all([
            fetch(`${API_URL}/api/trades`),
            fetch(`${API_URL}/api/prices`),
            fetch(`${API_URL}/api/stats`)
        ]);

        trades = await tradesRes.json();
        prices = await pricesRes.json();
        const stats = await statsRes.json();

        updateStats(stats);
        renderTrades();
    } catch (error) {
        console.error('Error loading data:', error);
    }
}

// Update statistics
function updateStats(stats) {
    const statElements = {
        'totalTrades': stats.total_trades || 0,
        'binanceTrades': stats.binance_trades || 0,
        'bybitTrades': stats.bybit_trades || 0,
        'runningTrades': stats.running_trades || 0,
        'waitingTrades': stats.waiting_trades || 0,
        'trackedPrices': stats.tracked_prices || 0
    };

    for (const [id, value] of Object.entries(statElements)) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }
}

// Render trades table
function renderTrades() {
    const container = document.getElementById('tradesTableContainer');
    if (!container) return;

    if (!trades || trades.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p style="font-size: 16px;">No trades found</p>
                <p style="font-size: 14px; margin-top: 8px;">Click "Add Trade" to subscribe to a new trading pair</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="trades-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Exchange</th>
                    <th>Pair</th>
                    <th>Mode</th>
                    <th>Current Price</th>
                    <th>Entry</th>
                    <th>Stop Loss</th>
                    <th>TP1</th>
                    <th>TP2</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${trades.map(trade => {
                    const currentPrice = prices[trade.pair] || 0;
                    return `
                        <tr>
                            <td>${trade.trade_id}</td>
                            <td><span class="badge badge-${trade.market}">${trade.market}</span></td>
                            <td><strong>${trade.pair}</strong></td>
                            <td><span class="badge badge-${trade.mod.toLowerCase()}">${trade.mod}</span></td>
                            <td class="price-live">${currentPrice > 0 ? currentPrice.toFixed(2) : 'N/A'}</td>
                            <td>${trade.entry.toFixed(2)}</td>
                            <td>${trade.sl.toFixed(2)}</td>
                            <td>${trade.tp1 > 0 ? trade.tp1.toFixed(2) : '-'}</td>
                            <td>${trade.tp2 > 0 ? trade.tp2.toFixed(2) : '-'}</td>
                            <td><span class="badge badge-${trade.status}">${trade.status}</span></td>
                            <td>
                                <button class="btn-edit" onclick="editTrade(${trade.trade_id})">Edit</button>
                                <button class="btn-delete" onclick="deleteTrade(${trade.trade_id})">Delete</button>
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
    container.innerHTML = html;
}

// Modal functions
function openAddTradeModal() {
    const modal = document.getElementById('addTradeModal');
    if (modal) modal.style.display = 'flex';
}

function closeAddTradeModal() {
    const modal = document.getElementById('addTradeModal');
    if (modal) modal.style.display = 'none';
    const form = document.getElementById('addTradeForm');
    if (form) form.reset();
}

// Add trade form handler
function setupAddTradeForm() {
    const form = document.getElementById('addTradeForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const trade = {
                id: Date.now(),
                market: document.getElementById('tradeMarket').value,
                tp_mode: document.getElementById('tradeMode').value,
                instruments: document.getElementById('tradePair').value.toUpperCase(),
                entry_target: parseFloat(document.getElementById('tradeEntry').value),
                stop_loss: parseFloat(document.getElementById('tradeSL').value),
                stop_loss_percentage: 0,
                stop_loss_price: 0,
                take_profit1: parseFloat(document.getElementById('tradeTP1').value) || 0,
                take_profit2: parseFloat(document.getElementById('tradeTP2').value) || 0,
                take_profit3: 0,
                take_profit4: 0,
                take_profit5: 0,
                take_profit6: 0,
                take_profit7: 0,
                take_profit8: 0,
                take_profit9: 0,
                take_profit10: 0,
                height_price: 0,
                status: 'waiting'
            };

            try {
                const response = await fetch(`${API_URL}/api/config-trade`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'add', trade })
                });

                const data = await response.json();
                if (data.status) {
                    closeAddTradeModal();
                    loadData();
                }
            } catch (error) {
                console.error('Error adding trade:', error);
                alert('Failed to add trade');
            }
        });
    }
}

// Edit trade
function editTrade(tradeId) {
    const trade = trades.find(t => t.trade_id === tradeId);
    if (!trade) return;

    // Populate form with trade data
    document.getElementById('tradeMarket').value = trade.market;
    document.getElementById('tradeMode').value = trade.mod;
    document.getElementById('tradePair').value = trade.pair;
    document.getElementById('tradeEntry').value = trade.entry;
    document.getElementById('tradeSL').value = trade.sl;
    document.getElementById('tradeTP1').value = trade.tp1 || '';
    document.getElementById('tradeTP2').value = trade.tp2 || '';

    // Change form to update mode
    const form = document.getElementById('addTradeForm');
    const modal = document.getElementById('addTradeModal');
    const modalTitle = document.querySelector('.modal-title');

    if (modalTitle) modalTitle.textContent = 'Edit Trade';
    if (modal) modal.style.display = 'flex';

    // Update form submit handler
    form.onsubmit = async (e) => {
        e.preventDefault();

        const updatedTrade = {
            id: tradeId,
            market: document.getElementById('tradeMarket').value,
            tp_mode: document.getElementById('tradeMode').value,
            instruments: document.getElementById('tradePair').value.toUpperCase(),
            entry_target: parseFloat(document.getElementById('tradeEntry').value),
            stop_loss: parseFloat(document.getElementById('tradeSL').value),
            stop_loss_percentage: 0,
            stop_loss_price: 0,
            take_profit1: parseFloat(document.getElementById('tradeTP1').value) || 0,
            take_profit2: parseFloat(document.getElementById('tradeTP2').value) || 0,
            status: trade.status
        };

        try {
            const response = await fetch(`${API_URL}/api/config-trade`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'update', trade: updatedTrade })
            });

            const data = await response.json();
            if (data.status) {
                closeAddTradeModal();
                loadData();
                // Reset form handler
                setupAddTradeForm();
                if (modalTitle) modalTitle.textContent = 'Add New Trade';
            }
        } catch (error) {
            console.error('Error updating trade:', error);
            alert('Failed to update trade');
        }
    };
}

// Delete trade
async function deleteTrade(tradeId) {
    if (!confirm(`Are you sure you want to delete trade ${tradeId}?`)) return;

    try {
        const response = await fetch(`${API_URL}/api/config-trade`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'delete', trade: tradeId })
        });

        const data = await response.json();
        if (data.status) {
            loadData();
        }
    } catch (error) {
        console.error('Error deleting trade:', error);
        alert('Failed to delete trade');
    }
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    const modal = document.getElementById('addTradeModal');
    if (e.target === modal) {
        closeAddTradeModal();
    }
});

// Initialize forms when page loads
document.addEventListener('DOMContentLoaded', () => {
    setupLoginForm();
    setupAddTradeForm();
});
