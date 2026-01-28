    // Mock data
    const users = [
      {
        id: 1,
        chatId: '6062724880',
        username: '@trader_mike',
        avatar: 'TM',
        avatarGradient: 'linear-gradient(135deg, #00D9E9, #3B82F6)',
        bot: 'Bot3',
        managerStatus: 'paid',
        shotStatus: 'trial',
        mode: 'active',
        strategy: 'Close at TP2',
        leverage: '20X',
        riskPercent: 2,
        apiBybit: true,
        apiBinance: false,
        signals: 47,
        trades: 23,
        winRate: 68,
        pnl: 234.50,
        lastActive: '2h ago',
        registered: 'Nov 15, 2025',
        managerStart: 'Nov 15, 2025',
        managerEnd: 'Dec 15, 2025',
        shotStart: 'Nov 20, 2025',
        shotEnd: 'Nov 27, 2025',
        baseBalance: 1000,
        maxRisk: 50,
        dailyReset: '00:00 UTC'
      },
      {
        id: 2,
        chatId: '2015272032',
        username: '@crypto_jane',
        avatar: 'CJ',
        avatarGradient: 'linear-gradient(135deg, #EC4899, #8B5CF6)',
        bot: 'Bot5',
        managerStatus: 'paid',
        shotStatus: 'paid',
        mode: 'passive',
        strategy: 'Partial Close',
        leverage: '10X',
        riskPercent: 1,
        apiBybit: true,
        apiBinance: true,
        signals: 89,
        trades: 56,
        winRate: 72,
        pnl: 1205.30,
        lastActive: '1h ago',
        registered: 'Oct 28, 2025',
        managerStart: 'Oct 28, 2025',
        managerEnd: 'Nov 28, 2025',
        shotStart: 'Nov 1, 2025',
        shotEnd: 'Dec 1, 2025',
        baseBalance: 2000,
        maxRisk: 100,
        dailyReset: '00:00 UTC'
      },
      {
        id: 3,
        chatId: '1083577641',
        username: null,
        avatar: '?',
        avatarGradient: 'var(--bg-hover)',
        bot: 'Bot2',
        managerStatus: 'free',
        shotStatus: null,
        mode: null,
        strategy: null,
        leverage: null,
        riskPercent: null,
        apiBybit: false,
        apiBinance: false,
        signals: 8,
        trades: 0,
        winRate: 0,
        pnl: 0,
        lastActive: '2d ago',
        registered: 'Dec 1, 2025',
        managerStart: 'Dec 1, 2025',
        managerEnd: null,
        shotStart: null,
        shotEnd: null,
        baseBalance: 0,
        maxRisk: 0,
        dailyReset: '00:00 UTC'
      },
      {
        id: 4,
        chatId: '8307579890',
        username: '@wolf_signals',
        avatar: 'WS',
        avatarGradient: 'linear-gradient(135deg, #F59E0B, #EF4444)',
        bot: 'Bot3',
        managerStatus: 'trial',
        shotStatus: 'trial',
        mode: 'active',
        strategy: 'Close at TP3',
        leverage: '50X',
        riskPercent: 3,
        apiBybit: true,
        apiBinance: false,
        signals: 23,
        trades: 12,
        winRate: 42,
        pnl: -89.20,
        lastActive: '5h ago',
        registered: 'Nov 20, 2025',
        managerStart: 'Nov 20, 2025',
        managerEnd: 'Nov 27, 2025',
        shotStart: 'Nov 22, 2025',
        shotEnd: 'Nov 29, 2025',
        baseBalance: 500,
        maxRisk: 25,
        dailyReset: '00:00 UTC'
      },
      {
        id: 5,
        chatId: '6255079467',
        username: '@diamond_hands',
        avatar: 'DH',
        avatarGradient: 'linear-gradient(135deg, #A3E635, #00D9E9)',
        bot: 'Bot4',
        managerStatus: 'paid',
        shotStatus: 'paid',
        mode: 'active',
        strategy: 'Close at TP1',
        leverage: '15X',
        riskPercent: 1.5,
        apiBybit: true,
        apiBinance: false,
        signals: 156,
        trades: 98,
        winRate: 78,
        pnl: 3420.00,
        lastActive: '30m ago',
        registered: 'Sep 10, 2025',
        managerStart: 'Sep 10, 2025',
        managerEnd: 'Oct 10, 2025',
        shotStart: 'Sep 15, 2025',
        shotEnd: 'Oct 15, 2025',
        baseBalance: 5000,
        maxRisk: 250,
        dailyReset: '00:00 UTC'
      }
    ];

    const bots = [
      { id: 1, name: 'SignalManager Bot2', token: '85682...', type: 'free', maxUsers: 500, currentUsers: 487, msgPerSec: 18.4, peakMsgSec24h: 28.7, status: 'warning', errors24h: 3 },
      { id: 2, name: 'SignalManager Bot3', token: '83993...', type: 'free', maxUsers: 500, currentUsers: 234, msgPerSec: 12.1, peakMsgSec24h: 22.3, status: 'healthy', errors24h: 0 },
      { id: 3, name: 'SignalManager Bot4', token: '80973...', type: 'free', maxUsers: 500, currentUsers: 156, msgPerSec: 8.7, peakMsgSec24h: 15.2, status: 'healthy', errors24h: 1 },
      { id: 4, name: 'SignalManager Bot5', token: '84424...', type: 'free', maxUsers: 500, currentUsers: 89, msgPerSec: 5.2, peakMsgSec24h: 11.8, status: 'healthy', errors24h: 0 },
      { id: 5, name: 'SignalManager Bot6', token: '84896...', type: 'paid', maxUsers: 100, currentUsers: 45, msgPerSec: 3.1, peakMsgSec24h: 8.4, status: 'healthy', errors24h: 0 },
      { id: 6, name: 'SignalManager Bot7', token: '84169...', type: 'paid', maxUsers: 100, currentUsers: 67, msgPerSec: 4.8, peakMsgSec24h: 9.2, status: 'healthy', errors24h: 2 }
    ];

    const providers = [
      { id: 1, name: 'Crypto Industry', channel: '@cryptoindustry', signals: 234, winRate: 72.5, avgPnl: 15.3, users: 45, lastSignal: '2h ago', status: 'active' },
      { id: 2, name: 'Wolfx Signals', channel: '@wolfxsignals', signals: 189, winRate: 65.2, avgPnl: 8.7, users: 38, lastSignal: '4h ago', status: 'active' },
      { id: 3, name: 'Bulls Signals', channel: '@bullsignals', signals: 156, winRate: 58.9, avgPnl: 5.2, users: 29, lastSignal: '1h ago', status: 'active' },
      { id: 4, name: 'GG-Shot Leak', channel: '@ggshotleak', signals: 98, winRate: 45.3, avgPnl: -2.1, users: 15, lastSignal: '6h ago', status: 'active' },
      { id: 5, name: 'Gorilla Crypto', channel: '@gorillacrypto', signals: 67, winRate: 71.8, avgPnl: 12.4, users: 22, lastSignal: '3h ago', status: 'active' }
    ];

    const trades = [
      { id: 1, userId: '6062724880', username: '@trader_mike', symbol: 'BTCUSDT', direction: 'long', entry: 43250, current: 43890, tp1: 43800, tp2: 44200, tp3: 44800, sl: 42800, status: 'active', pnl: 145.20, provider: 'Crypto Industry', openedAt: '2h ago' },
      { id: 2, userId: '2015272032', username: '@crypto_jane', symbol: 'ETHUSDT', direction: 'short', entry: 2280, current: 2250, tp1: 2250, tp2: 2200, tp3: 2150, sl: 2320, status: 'tp1_hit', pnl: 89.50, provider: 'Wolfx Signals', openedAt: '5h ago' },
      { id: 3, userId: '8307579890', username: '@wolf_signals', symbol: 'SOLUSDT', direction: 'long', entry: 98.5, current: 96.2, tp1: 102, tp2: 108, tp3: 115, sl: 95, status: 'active', pnl: -45.30, provider: 'Bulls Signals', openedAt: '1h ago' },
      { id: 4, userId: '6255079467', username: '@diamond_hands', symbol: 'XRPUSDT', direction: 'long', entry: 0.62, current: 0.68, tp1: 0.65, tp2: 0.70, tp3: 0.75, sl: 0.58, status: 'tp2_hit', pnl: 234.00, provider: 'Crypto Industry', openedAt: '8h ago' }
    ];

    const notifications = [
      { id: 1, type: 'success', title: 'New user registered', message: 'User 7182845029 via Bot4', time: '15 minutes ago', unread: true },
      { id: 2, type: 'warning', title: 'Bot capacity warning', message: 'Bot2 reached 97% capacity', time: '1 hour ago', unread: true },
      { id: 3, type: 'success', title: 'User upgraded', message: '@crypto_jane → SignalShot', time: '2 hours ago', unread: true },
      { id: 4, type: 'error', title: 'API Error', message: 'Binance API failed for user 2015272032', time: '3 hours ago', unread: false },
      { id: 5, type: 'success', title: 'Trade hit TP2', message: 'BTCUSDT LONG for @diamond_hands', time: '4 hours ago', unread: false }
    ];

    const systemLogs = [
      { time: '14:32:15', level: 'error', source: 'Telegram API', message: 'Rate limit exceeded on Bot2, retry 5s' },
      { time: '14:30:22', level: 'warning', source: 'Parser', message: 'Unknown signal format from "New Group"' },
      { time: '14:28:10', level: 'info', source: 'Bybit API', message: 'Trade executed: 6062724880 BTCUSDT LONG' },
      { time: '14:25:33', level: 'error', source: 'Binance API', message: 'API key invalid for user 2015272032' },
      { time: '14:22:45', level: 'info', source: 'System', message: 'Bot3 health check: OK' },
      { time: '14:20:18', level: 'debug', source: 'Auth', message: 'Session refresh for admin@signalvision' }
    ];

    const signals = [
      { time: '14:32', provider: 'Crypto Industry', raw: 'BTCUSDT LONG Entry: 43250 TP: 43800/44200/44800 SL: 42800', parsed: 'BTC/LONG/43250/TP:44K', status: 'ok', users: 45, trades: 12 },
      { time: '14:15', provider: 'Unknown', raw: 'Buy BTC now!!! 🚀🚀🚀', parsed: '—', status: 'error', users: 0, trades: 0 },
      { time: '13:58', provider: 'Wolfx', raw: 'ETH Short @ 2280 SL 2320 TP 2250/2200/2150', parsed: 'ETH/SHORT/2280/SL:2320', status: 'partial', users: 38, trades: 8 }
    ];

    // Global variables
    let currentPage = 'dashboard';
    let selectedUsers = new Set();
    let currentUserPage = 1;
    let pageSize = 20;
    let currentTradeTab = 'active';
    let currentSignalsTab = 'all';
    let charts = {};

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize icons
      lucide.createIcons();

      // Set current date (only if element exists)
      const currentDateElement = document.getElementById('currentDate');
      if (currentDateElement) {
        currentDateElement.textContent = new Date().toLocaleDateString('en-US', {
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        });
      }

      // Load dashboard data (only if dashboard exists)
      if (document.getElementById('page-dashboard')) {
        loadDashboard();
        loadUsersTable();
        loadProviders();
        loadTrades();
        loadNotifications();
        loadSystemLogs();
        loadSignals();
        loadBots();

        // Initialize charts
        initCharts();
      }

      // Setup event listeners
      setupEventListeners();
    });

    // Toggle sidebar
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const isCollapsed = sidebar.classList.contains('collapsed');
      
      if (isCollapsed) {
        sidebar.classList.remove('collapsed');
        sidebar.querySelector('.menu-toggle i').setAttribute('data-lucide', 'chevron-left');
      } else {
        sidebar.classList.add('collapsed');
        sidebar.querySelector('.menu-toggle i').setAttribute('data-lucide', 'chevron-right');
      }
      lucide.createIcons();
    }

    // Toggle mobile menu (only if element exists)
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
          sidebar.classList.toggle('active');
        }
      });
    }

    // Toggle submenu
    function toggleSubmenu(name) {
      const submenu = document.getElementById('submenu-' + name);
      const chevron = submenu.previousElementSibling.querySelector('.chevron');
      
      submenu.classList.toggle('expanded');
      
      if (submenu.classList.contains('expanded')) {
        chevron.setAttribute('data-lucide', 'chevron-up');
      } else {
        chevron.setAttribute('data-lucide', 'chevron-down');
      }
      lucide.createIcons();
    }

    // Show page
    function showPage(pageId) {
      // Hide all pages
      document.querySelectorAll('.page-section').forEach(page => {
        page.classList.remove('active');
      });

      // Show selected page
      const targetPage = document.getElementById('page-' + pageId);
      if (targetPage) {
        targetPage.classList.add('active');
      } else {
        // Default to dashboard if page not found
        document.getElementById('page-dashboard').classList.add('active');
        pageId = 'dashboard';
      }

      // Update menu active states
      document.querySelectorAll('.menu-item, .submenu-item').forEach(item => {
        item.classList.remove('active');
      });

      // Find and activate the corresponding menu item
      const menuItem = document.querySelector(`[data-page="${pageId}"]`);
      if (menuItem) {
        menuItem.classList.add('active');
        
        // If it's a submenu item, also expand the parent
        if (menuItem.classList.contains('submenu-item')) {
          const submenu = menuItem.closest('.submenu');
          if (submenu) {
            submenu.classList.add('expanded');
            const parentBtn = submenu.previousElementSibling;
            if (parentBtn) {
              parentBtn.classList.add('active');
              parentBtn.querySelector('.chevron').setAttribute('data-lucide', 'chevron-up');
            }
          }
        }
      }

      // Update breadcrumb
      const pageTitles = {
        'dashboard': 'Dashboard',
        'users-all': 'All Users',
        'users-manager': 'Manager Users',
        'users-shot': 'Shot Users',
        'bots': 'Telegram Bots',
        'providers': 'Signal Providers',
        'trades': 'Trades',
        'signals': 'Signals Log',
        'analytics': 'Analytics',
        'logs': 'System Logs',
        'settings': 'Settings',
        'roles': 'Roles & Permissions'
      };
      
      document.getElementById('breadcrumbCurrent').textContent = pageTitles[pageId] || pageId.replace('-', ' ');
      
      // Update current page
      currentPage = pageId;
      
      // Load page-specific data
      switch(pageId) {
        case 'users-all':
          loadFullUsersTable();
          break;
        case 'providers':
          loadProviders();
          break;
        case 'trades':
          loadTrades();
          break;
        case 'analytics':
          loadAnalytics();
          break;
        case 'logs':
          loadSystemLogs();
          break;
        case 'signals':
          loadSignals();
          break;
        case 'bots':
          loadBotsPage();
          break;
      }
      
      // Re-initialize icons for new content
      lucide.createIcons();
      
      // Close mobile sidebar if open
      if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
      }
    }

    // Load dashboard data
    function loadDashboard() {
      // Load bots grid
      const botsGrid = document.getElementById('botsGrid');
      botsGrid.innerHTML = '';
      
      bots.forEach(bot => {
        const userPercentage = (bot.currentUsers / bot.maxUsers) * 100;
        const msgPercentage = (bot.msgPerSec / 30) * 100;
        
        const botCard = document.createElement('div');
        botCard.className = 'bot-card';
        botCard.onclick = () => openBotModal(bot);
        
        botCard.innerHTML = `
          <div class="bot-header">
            <div class="bot-name">
              <i data-lucide="bot"></i>
              ${bot.name.split(' ')[1]}
            </div>
            <div class="bot-status ${bot.status}"></div>
          </div>
          <div class="bot-metric">
            <div class="bot-metric-header">
              <span class="bot-metric-label">Users</span>
              <span class="bot-metric-value">${bot.currentUsers}/${bot.maxUsers}</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill ${userPercentage > 90 ? 'orange' : 'cyan'}" style="width: ${userPercentage}%;"></div>
            </div>
          </div>
          <div class="bot-metric">
            <div class="bot-metric-header">
              <span class="bot-metric-label">Msg/sec</span>
              <span class="bot-metric-value">${bot.msgPerSec.toFixed(1)}/30</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill lime" style="width: ${msgPercentage}%;"></div>
            </div>
          </div>
          <div class="bot-footer">
            <span class="bot-errors">${bot.errors24h > 0 ? '⚠ ' + bot.errors24h + ' errors (24h)' : '✓ No errors'}</span>
            <span class="bot-type-badge ${bot.type}">${bot.type}</span>
          </div>
        `;
        
        botsGrid.appendChild(botCard);
      });
      
      // Load activity list
      const activityList = document.getElementById('activityList');
      activityList.innerHTML = '';
      
      const activities = [
        { icon: 'trending-up', color: 'lime', message: 'User @crypto_jane upgraded to SignalShot Paid', time: '15m ago' },
        { icon: 'user-plus', color: 'cyan', message: 'New user 7182845029 registered via Bot4', time: '32m ago' },
        { icon: 'alert-triangle', color: 'orange', message: 'Bot2 reached 97% capacity', time: '1h ago' },
        { icon: 'target', color: 'green', message: 'Trade BTCUSDT LONG hit TP2 for @diamond_hands', time: '2h ago' },
        { icon: 'x-circle', color: 'red', message: 'Failed to parse signal from unknown provider', time: '3h ago' }
      ];
      
      activities.forEach(activity => {
        const activityItem = document.createElement('div');
        activityItem.className = 'activity-item';
        
        activityItem.innerHTML = `
          <div class="activity-icon" style="background: rgba(var(--${activity.color}-rgb), 0.2);">
            <i data-lucide="${activity.icon}" style="color: var(--${activity.color});"></i>
          </div>
          <div class="activity-content">
            <div class="activity-message">${activity.message}</div>
            <div class="activity-time">${activity.time}</div>
          </div>
        `;
        
        activityList.appendChild(activityItem);
      });
      
      // Load users table
      loadUsersTable();
    }

    // Load users table for dashboard
    function loadUsersTable() {
      const table = document.getElementById('usersTable');
      if (!table) return;
      
      const tbody = table.querySelector('tbody') || document.createElement('tbody');
      tbody.innerHTML = '';
      
      // Add header
      const thead = table.querySelector('thead') || document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th>User</th>
          <th>Bot</th>
          <th>Manager</th>
          <th>Shot</th>
          <th>Mode</th>
          <th>Strategy</th>
          <th>Signals</th>
          <th>PnL</th>
          <th>Activity</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      `;
      
      // Add rows
      users.slice(0, 5).forEach(user => {
        const row = document.createElement('tr');
        
        const username = user.username || user.chatId;
        const displayName = user.username ? `@${user.username.replace('@', '')}` : user.chatId;
        
        row.innerHTML = `
          <td>
            <div class="user-cell">
              <div class="user-avatar-small" style="background: ${user.avatarGradient};">${user.avatar}</div>
              <div class="user-details">
                <span class="user-username">${displayName}</span>
                <span class="user-chatid">${user.chatId}</span>
              </div>
            </div>
          </td>
          <td><span style="color: var(--cyan); font-size: 12px;">${user.bot}</span></td>
          <td><span class="badge badge-${user.managerStatus}">${user.managerStatus === 'paid' ? 'Paid' : user.managerStatus === 'trial' ? 'Trial' : 'Free'}</span></td>
          <td>${user.shotStatus ? `<span class="badge badge-${user.shotStatus}">${user.shotStatus === 'paid' ? 'Paid' : 'Trial'}</span>` : '<span style="color: var(--text-muted); font-size: 12px;">—</span>'}</td>
          <td>
            ${user.mode ? `
              <div class="mode-cell ${user.mode}">
                <i data-lucide="${user.mode === 'active' ? 'zap' : 'toggle-left'}"></i>
                ${user.mode === 'active' ? 'Active' : 'Passive'}
              </div>
            ` : '<span style="color: var(--text-muted); font-size: 12px;">—</span>'}
          </td>
          <td>
            ${user.strategy ? `
              <div class="strategy-cell">${user.strategy}</div>
              <div class="strategy-details">${user.leverage || ''} ${user.riskPercent ? '· ' + user.riskPercent + '%' : ''}</div>
            ` : '<span style="color: var(--text-muted); font-size: 12px;">—</span>'}
          </td>
          <td>${user.signals}</td>
          <td><span class="pnl-cell ${user.pnl > 0 ? 'positive' : user.pnl < 0 ? 'negative' : 'neutral'}">${user.pnl > 0 ? '+' : ''}${user.pnl.toFixed(2)} USDT</span></td>
          <td><span style="color: var(--text-gray); font-size: 12px;">${user.lastActive}</span></td>
          <td>
            <div class="actions-cell">
              <button class="action-btn view" onclick="openUserModal(${user.id})">
                <i data-lucide="eye"></i>
              </button>
              <button class="action-btn edit" onclick="editUserModal(${user.id})">
                <i data-lucide="edit"></i>
              </button>
            </div>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      if (!table.querySelector('thead')) table.appendChild(thead);
      if (!table.querySelector('tbody')) table.appendChild(tbody);
      
      lucide.createIcons();
    }

    // Load full users table for users page
    function loadFullUsersTable() {
      const table = document.getElementById('fullUsersTable');
      if (!table) return;
      
      const tbody = table.querySelector('tbody') || document.createElement('tbody');
      tbody.innerHTML = '';
      
      // Add header
      const thead = table.querySelector('thead') || document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th class="checkbox-cell"><div class="checkbox" onclick="toggleSelectAll()"></div></th>
          <th>User</th>
          <th>Bot</th>
          <th>Manager</th>
          <th>Shot</th>
          <th>Mode</th>
          <th>Strategy</th>
          <th>Leverage</th>
          <th>Risk</th>
          <th>API</th>
          <th style="text-align: center;">Signals</th>
          <th style="text-align: center;">Trades</th>
          <th style="text-align: center;">Win Rate</th>
          <th style="text-align: right;">PnL</th>
          <th>Last Active</th>
          <th>Registered</th>
          <th style="text-align: center;">Actions</th>
        </tr>
      `;
      
      // Calculate pagination
      const startIndex = (currentUserPage - 1) * pageSize;
      const endIndex = startIndex + pageSize;
      const paginatedUsers = users.slice(startIndex, endIndex);
      
      // Add rows
      paginatedUsers.forEach(user => {
        const row = document.createElement('tr');
        const isSelected = selectedUsers.has(user.id);
        
        const username = user.username || user.chatId;
        const displayName = user.username ? `@${user.username.replace('@', '')}` : user.chatId;
        
        // Calculate win rate color
        let winRateClass = 'neutral';
        if (user.winRate >= 65) winRateClass = 'good';
        else if (user.winRate >= 50) winRateClass = 'medium';
        else if (user.winRate > 0) winRateClass = 'bad';
        
        row.innerHTML = `
          <td class="checkbox-cell">
            <div class="checkbox ${isSelected ? 'checked' : ''}" onclick="toggleUserSelection(${user.id}, event)"></div>
          </td>
          <td>
            <div class="user-cell">
              <div class="user-avatar-small" style="background: ${user.avatarGradient};">${user.avatar}</div>
              <div class="user-details">
                <span class="user-username">${displayName}</span>
                <span class="user-chatid">${user.chatId}</span>
              </div>
            </div>
          </td>
          <td><span class="badge badge-info">${user.bot}</span></td>
          <td><span class="badge badge-${user.managerStatus}">${user.managerStatus === 'paid' ? 'Paid' : user.managerStatus === 'trial' ? 'Trial' : 'Free'}</span></td>
          <td>${user.shotStatus ? `<span class="badge badge-${user.shotStatus}">${user.shotStatus === 'paid' ? 'Paid' : 'Trial'}</span>` : '<span style="color: var(--text-muted); font-size: 12px;">—</span>'}</td>
          <td>
            ${user.mode ? `
              <div class="mode-cell ${user.mode}">
                <i data-lucide="${user.mode === 'active' ? 'zap' : 'toggle-left'}"></i>
                ${user.mode === 'active' ? 'Active' : 'Passive'}
              </div>
            ` : '<span style="color: var(--text-muted); font-size: 12px;">—</span>'}
          </td>
          <td><span style="font-size: 12px;">${user.strategy || '—'}</span></td>
          <td><span style="font-size: 12px; color: ${user.leverage ? 'var(--orange)' : 'var(--text-muted)'};">${user.leverage || '—'}</span></td>
          <td><span style="font-size: 12px;">${user.riskPercent ? user.riskPercent + '%' : '—'}</span></td>
          <td>
            <div class="api-status ${user.apiBybit ? 'connected' : 'disconnected'}">
              <i data-lucide="${user.apiBybit ? 'check-circle' : 'x-circle'}"></i>
              Bybit
            </div>
            <div class="api-status ${user.apiBinance ? 'connected' : 'disconnected'}" style="margin-top: 4px;">
              <i data-lucide="${user.apiBinance ? 'check-circle' : 'x-circle'}"></i>
              Binance
            </div>
          </td>
          <td style="text-align: center;">${user.signals}</td>
          <td style="text-align: center;">${user.trades}</td>
          <td style="text-align: center;">
            ${user.winRate > 0 ? `<span class="winrate-badge ${winRateClass}">${user.winRate}%</span>` : '<span style="color: var(--text-muted);">—</span>'}
          </td>
          <td style="text-align: right;">
            <span class="pnl-cell ${user.pnl > 0 ? 'positive' : user.pnl < 0 ? 'negative' : 'neutral'}">
              ${user.pnl > 0 ? '+' : ''}$${Math.abs(user.pnl).toFixed(2)}
            </span>
          </td>
          <td><span style="color: var(--text-gray); font-size: 12px;">${user.lastActive}</span></td>
          <td><span style="color: var(--text-gray); font-size: 12px;">${user.registered}</span></td>
          <td>
            <div class="actions-cell">
              <button class="action-btn view" onclick="openUserModal(${user.id})" title="View">
                <i data-lucide="eye"></i>
              </button>
              <button class="action-btn message" onclick="messageUser(${user.id})" title="Message">
                <i data-lucide="message-square"></i>
              </button>
              <button class="action-btn edit" onclick="editUserModal(${user.id})" title="Edit">
                <i data-lucide="edit"></i>
              </button>
              <div class="dropdown" style="position: relative;">
                <button class="action-btn" onclick="toggleUserDropdown(${user.id})" title="More">
                  <i data-lucide="more-vertical"></i>
                </button>
                <div class="dropdown-menu" id="dropdown-${user.id}" style="display: none; position: absolute; right: 0; top: 100%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 8px; z-index: 1000; min-width: 150px;">
                  <button class="dropdown-item" onclick="resetUserSettings(${user.id})" style="width: 100%; text-align: left; padding: 8px; background: none; border: none; color: var(--text-white); cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                    Reset Settings
                  </button>
                  <button class="dropdown-item" onclick="banUser(${user.id})" style="width: 100%; text-align: left; padding: 8px; background: none; border: none; color: var(--red); cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="user-x" style="width: 14px; height: 14px;"></i>
                    Ban User
                  </button>
                </div>
              </div>
            </div>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      if (!table.querySelector('thead')) table.appendChild(thead);
      if (!table.querySelector('tbody')) table.appendChild(tbody);
      
      // Update pagination info
      const paginationInfo = document.getElementById('paginationInfo');
      if (paginationInfo) {
        paginationInfo.textContent =
          `Showing ${startIndex + 1}-${Math.min(endIndex, users.length)} of ${users.length} users`;
      }
      
      // Update pagination numbers
      updatePaginationNumbers();
      
      // Update bulk actions bar
      updateBulkActionsBar();
      
      lucide.createIcons();
    }

    // Toggle user selection
    function toggleUserSelection(userId, event) {
      if (event) event.stopPropagation();
      
      if (selectedUsers.has(userId)) {
        selectedUsers.delete(userId);
      } else {
        selectedUsers.add(userId);
      }
      
      // Update checkbox
      const checkbox = event?.target.closest('.checkbox');
      if (checkbox) {
        checkbox.classList.toggle('checked');
      }
      
      updateBulkActionsBar();
    }

    // Toggle select all
    function toggleSelectAll() {
      const checkboxes = document.querySelectorAll('.checkbox:not(:first-child)');
      const allSelected = selectedUsers.size === users.length;
      
      if (allSelected) {
        selectedUsers.clear();
        checkboxes.forEach(cb => cb.classList.remove('checked'));
      } else {
        users.forEach(user => selectedUsers.add(user.id));
        checkboxes.forEach(cb => cb.classList.add('checked'));
      }
      
      updateBulkActionsBar();
    }

    // Update bulk actions bar
    function updateBulkActionsBar() {
      const bar = document.getElementById('bulkActionsBar');
      const count = document.getElementById('bulkCount');
      if (!bar || !count) return;

      if (selectedUsers.size > 0) {
        bar.classList.add('active');
        count.textContent = `${selectedUsers.size} users selected`;
      } else {
        bar.classList.remove('active');
      }
    }

    // Update pagination numbers
    function updatePaginationNumbers() {
      const totalPages = Math.ceil(users.length / pageSize);
      const container = document.getElementById('paginationNumbers');
      if (!container) return;

      container.innerHTML = '';
      
      // Always show first page
      addPageNumber(1);
      
      // Calculate range around current page
      let start = Math.max(2, currentUserPage - 1);
      let end = Math.min(totalPages - 1, currentUserPage + 1);
      
      // Add ellipsis if needed
      if (start > 2) {
        container.innerHTML += '<span style="padding: 0 8px;">...</span>';
      }
      
      // Add page numbers in range
      for (let i = start; i <= end; i++) {
        addPageNumber(i);
      }
      
      // Add ellipsis if needed
      if (end < totalPages - 1) {
        container.innerHTML += '<span style="padding: 0 8px;">...</span>';
      }
      
      // Always show last page if not already shown
      if (totalPages > 1 && end < totalPages) {
        addPageNumber(totalPages);
      }
      
      // Update button states
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');
      if (prevBtn) prevBtn.disabled = currentUserPage === 1;
      if (nextBtn) nextBtn.disabled = currentUserPage === totalPages;
    }

    function addPageNumber(page) {
      const container = document.getElementById('paginationNumbers');
      if (!container) return;

      const pageNumber = document.createElement('div');
      pageNumber.className = `page-number ${page === currentUserPage ? 'active' : ''}`;
      pageNumber.textContent = page;
      pageNumber.onclick = () => goToPage(page);
      container.appendChild(pageNumber);
    }

    function goToPage(page) {
      currentUserPage = page;
      loadFullUsersTable();
    }

    function prevPage() {
      if (currentUserPage > 1) {
        currentUserPage--;
        loadFullUsersTable();
      }
    }

    function nextPage() {
      const totalPages = Math.ceil(users.length / pageSize);
      if (currentUserPage < totalPages) {
        currentUserPage++;
        loadFullUsersTable();
      }
    }

    // Load providers
    function loadProviders() {
      const tbody = document.getElementById('providersTableBody');
      if (!tbody) return;
      
      tbody.innerHTML = '';
      
      providers.forEach(provider => {
        const row = document.createElement('tr');
        
        // Determine win rate badge class
        let winRateClass = 'medium';
        if (provider.winRate >= 65) winRateClass = 'good';
        else if (provider.winRate < 50) winRateClass = 'bad';
        
        row.innerHTML = `
          <td><span style="font-size: 14px; font-weight: 500;">${provider.name}</span></td>
          <td><span style="color: var(--cyan);">${provider.channel}</span></td>
          <td style="text-align: center;">${provider.signals}</td>
          <td style="text-align: center;"><span class="winrate-badge ${winRateClass}">${provider.winRate}%</span></td>
          <td style="text-align: center;"><span class="pnl-cell ${provider.avgPnl > 0 ? 'positive' : 'negative'}">${provider.avgPnl > 0 ? '+' : ''}${provider.avgPnl}%</span></td>
          <td style="text-align: center;">${provider.users}</td>
          <td style="text-align: center;"><span style="color: var(--text-gray);">${provider.lastSignal}</span></td>
          <td style="text-align: center;"><span class="badge badge-success">Active</span></td>
          <td style="text-align: center;">
            <button class="action-btn view" onclick="openProviderModal(${provider.id})" title="View">
              <i data-lucide="eye"></i>
            </button>
            <button class="action-btn" onclick="disableProvider(${provider.id})" title="Disable">
              <i data-lucide="toggle-right"></i>
            </button>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      lucide.createIcons();
    }

    // Load trades
    function loadTrades() {
      const tbody = document.getElementById('tradesTableBody');
      if (!tbody) return;
      
      tbody.innerHTML = '';
      
      // Filter trades based on current tab
      let filteredTrades = trades;
      if (currentTradeTab === 'closed') {
        filteredTrades = trades.filter(t => t.status !== 'active');
      } else if (currentTradeTab === 'history') {
        // In a real app, this would fetch from a larger dataset
        filteredTrades = [...trades, ...trades.map(t => ({...t, id: t.id + 10}))];
      }
      
      filteredTrades.forEach(trade => {
        const row = document.createElement('tr');
        
        // Calculate if price is up or down
        const priceDiff = trade.current - trade.entry;
        const priceDirection = priceDiff >= 0 ? 'up' : 'down';
        
        // Format targets
        const tp1Struck = trade.status === 'tp1_hit' || trade.status === 'tp2_hit' || trade.status === 'tp3_hit';
        const tp2Struck = trade.status === 'tp2_hit' || trade.status === 'tp3_hit';
        const tp3Struck = trade.status === 'tp3_hit';
        
        row.innerHTML = `
          <td>${trade.id}</td>
          <td><span style="color: var(--cyan);">${trade.username}</span></td>
          <td>
            <div class="trade-symbol">
              <span class="direction-badge ${trade.direction}">${trade.direction.toUpperCase()}</span>
              <span class="symbol-name">${trade.symbol}</span>
            </div>
          </td>
          <td style="text-align: right;">${formatNumber(trade.entry)}</td>
          <td style="text-align: right;"><span class="price-cell ${priceDirection}">${formatNumber(trade.current)}</span></td>
          <td>
            <div class="targets-cell">
              <div class="targets-tp ${tp1Struck ? 'struck' : ''}">${formatNumber(trade.tp1)}</div>
              <div class="targets-tp ${tp2Struck ? 'struck' : ''}">${formatNumber(trade.tp2)}</div>
              <div class="targets-tp ${tp3Struck ? 'struck' : ''}">${formatNumber(trade.tp3)}</div>
            </div>
          </td>
          <td style="text-align: right;">${formatNumber(trade.sl)}</td>
          <td>
            <span class="trade-status ${trade.status === 'active' ? 'active' : 'hit'}">
              ${trade.status === 'active' ? 'Active' : 
                trade.status === 'tp1_hit' ? 'TP1 Hit' :
                trade.status === 'tp2_hit' ? 'TP2 Hit' :
                trade.status === 'tp3_hit' ? 'TP3 Hit' : 'SL Hit'}
            </span>
          </td>
          <td style="text-align: right;"><span class="pnl-cell ${trade.pnl >= 0 ? 'positive' : 'negative'}">${trade.pnl >= 0 ? '+' : ''}${trade.pnl.toFixed(2)}</span></td>
          <td><span style="color: var(--text-gray);">${trade.provider}</span></td>
          <td><span style="color: var(--text-gray);">${trade.openedAt}</span></td>
          <td>
            <div class="actions-cell">
              <button class="action-btn view" onclick="viewTrade(${trade.id})" title="View">
                <i data-lucide="eye"></i>
              </button>
              ${trade.status === 'active' ? `
                <button class="action-btn" onclick="closeTrade(${trade.id})" title="Close">
                  <i data-lucide="x-circle"></i>
                </button>
              ` : ''}
            </div>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      lucide.createIcons();
    }

    function setTradeTab(tab) {
      currentTradeTab = tab;
      
      // Update tabs
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      event.target.classList.add('active');
      
      // Update tab labels (in a real app, counts would be dynamic)
      const tabLabels = {
        active: 'Active Trades (12)',
        closed: 'Closed Today (34)',
        history: 'All History'
      };
      
      event.target.textContent = tabLabels[tab];
      
      // Reload trades
      loadTrades();
    }

    // Load signals
    function loadSignals() {
      const tbody = document.getElementById('signalsTableBody');
      if (!tbody) return;
      
      tbody.innerHTML = '';
      
      // Filter signals based on current tab
      let filteredSignals = signals;
      if (currentSignalsTab === 'parsed') {
        filteredSignals = signals.filter(s => s.status === 'ok');
      } else if (currentSignalsTab === 'errors') {
        filteredSignals = signals.filter(s => s.status === 'error');
      }
      
      filteredSignals.forEach(signal => {
        const row = document.createElement('tr');
        
        // Status badge
        let statusBadge = '';
        if (signal.status === 'ok') {
          statusBadge = '<span class="badge badge-success">✅ OK</span>';
        } else if (signal.status === 'error') {
          statusBadge = '<span class="badge badge-error">❌ Error</span>';
        } else if (signal.status === 'partial') {
          statusBadge = '<span class="badge badge-warning">⚠️ Partial</span>';
        }
        
        row.innerHTML = `
          <td><span style="color: var(--text-white);">${signal.time}</span></td>
          <td><span style="color: var(--cyan);">${signal.provider}</span></td>
          <td><span style="color: var(--text-gray); font-size: 12px;">${signal.raw}</span></td>
          <td><span style="color: var(--text-white); font-family: monospace; font-size: 12px;">${signal.parsed}</span></td>
          <td>${statusBadge}</td>
          <td style="text-align: center;">${signal.users}</td>
          <td style="text-align: center;">${signal.trades}</td>
          <td>
            <div class="actions-cell">
              <button class="action-btn" onclick="viewRawSignal('${signal.raw}')" title="View Raw">
                <i data-lucide="file-text"></i>
              </button>
              ${signal.status === 'error' ? `
                <button class="action-btn" onclick="createParserRule('${signal.raw}')" title="Create Parser Rule">
                  <i data-lucide="plus-circle"></i>
                </button>
              ` : ''}
            </div>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      lucide.createIcons();
    }

    function setSignalsTab(tab) {
      currentSignalsTab = tab;
      
      // Update tabs
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      event.target.classList.add('active');
      
      // Reload signals
      loadSignals();
    }

    // Load system logs
    function loadSystemLogs() {
      const container = document.getElementById('logsContainer');
      if (!container) return;
      
      container.innerHTML = '';
      
      systemLogs.forEach(log => {
        const logItem = document.createElement('div');
        logItem.className = 'log-item';
        
        logItem.innerHTML = `
          <div class="log-time">${log.time}</div>
          <div class="log-level ${log.level}">${log.level.toUpperCase()}</div>
          <div class="log-source">${log.source}</div>
          <div class="log-message">${log.message}</div>
        `;
        
        container.appendChild(logItem);
      });
    }

    // Load notifications
    function loadNotifications() {
      const container = document.getElementById('notificationsBody');
      if (!container) return;
      
      container.innerHTML = '';
      
      notifications.forEach(notification => {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.unread ? 'unread' : ''}`;
        
        // Icon based on type
        let icon = 'info';
        let iconColor = 'blue';
        if (notification.type === 'success') {
          icon = 'check-circle';
          iconColor = 'green';
        } else if (notification.type === 'warning') {
          icon = 'alert-triangle';
          iconColor = 'orange';
        } else if (notification.type === 'error') {
          icon = 'x-circle';
          iconColor = 'red';
        }
        
        item.innerHTML = `
          <div class="notification-icon" style="background: rgba(var(--${iconColor}-rgb), 0.2);">
            <i data-lucide="${icon}" style="color: var(--${iconColor});"></i>
          </div>
          <div class="notification-content">
            <div class="notification-title">${notification.title}</div>
            <div class="notification-message">${notification.message}</div>
            <div class="notification-time">${notification.time}</div>
          </div>
        `;
        
        container.appendChild(item);
      });
      
      // Update notification dot
      const unreadCount = notifications.filter(n => n.unread).length;
      const dot = document.querySelector('.notification-dot');
      if (unreadCount > 0) {
        dot.style.display = 'block';
      } else {
        dot.style.display = 'none';
      }
      
      lucide.createIcons();
    }

    // Load bots for bots page
    function loadBotsPage() {
      const container = document.getElementById('botsPageGrid');
      if (!container) return;
      
      container.innerHTML = '';
      
      bots.forEach(bot => {
        const userPercentage = (bot.currentUsers / bot.maxUsers) * 100;
        const msgPercentage = (bot.msgPerSec / 30) * 100;
        const peakPercentage = (bot.peakMsgSec24h / 30) * 100;
        
        const botCard = document.createElement('div');
        botCard.className = 'bot-card';
        botCard.style.padding = '20px';
        botCard.onclick = () => openBotModal(bot);
        
        botCard.innerHTML = `
          <div class="bot-header" style="margin-bottom: 16px;">
            <div class="bot-name" style="font-size: 16px;">
              <i data-lucide="bot"></i>
              ${bot.name}
            </div>
            <div class="bot-status ${bot.status}"></div>
          </div>
          <div style="display: flex; gap: 10px; margin-bottom: 12px;">
            <span class="bot-type-badge ${bot.type}">${bot.type}</span>
            <span style="font-size: 11px; color: var(--text-muted);">Token: ${bot.token}</span>
          </div>
          <div class="bot-metric" style="margin-bottom: 12px;">
            <div class="bot-metric-header">
              <span class="bot-metric-label">Users Capacity</span>
              <span class="bot-metric-value">${bot.currentUsers}/${bot.maxUsers} (${Math.round(userPercentage)}%)</span>
            </div>
            <div class="progress-bar" style="height: 6px;">
              <div class="progress-fill ${userPercentage > 90 ? 'orange' : userPercentage > 70 ? 'lime' : 'cyan'}" style="width: ${userPercentage}%;"></div>
            </div>
          </div>
          <div class="bot-metric" style="margin-bottom: 12px;">
            <div class="bot-metric-header">
              <span class="bot-metric-label">Messages/sec</span>
              <span class="bot-metric-value">${bot.msgPerSec.toFixed(1)}/30 (${Math.round(msgPercentage)}%)</span>
            </div>
            <div class="progress-bar" style="height: 6px;">
              <div class="progress-fill ${msgPercentage > 90 ? 'orange' : msgPercentage > 70 ? 'lime' : 'cyan'}" style="width: ${msgPercentage}%;"></div>
            </div>
          </div>
          <div class="bot-metric" style="margin-bottom: 12px;">
            <div class="bot-metric-header">
              <span class="bot-metric-label">Peak Msg/sec (24h)</span>
              <span class="bot-metric-value">${bot.peakMsgSec24h.toFixed(1)}/30 (${Math.round(peakPercentage)}%)</span>
            </div>
            <div class="progress-bar" style="height: 6px;">
              <div class="progress-fill ${peakPercentage > 90 ? 'orange' : peakPercentage > 70 ? 'lime' : 'cyan'}" style="width: ${peakPercentage}%;"></div>
            </div>
          </div>
          <div class="bot-footer" style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border);">
            <span class="bot-errors" style="color: ${bot.errors24h > 0 ? 'var(--orange)' : 'var(--green)'};">
              ${bot.errors24h > 0 ? '⚠ ' + bot.errors24h + ' errors (24h)' : '✓ No errors'}
            </span>
            <button class="btn btn-icon" onclick="editBot(${bot.id}, event)">
              <i data-lucide="edit"></i>
            </button>
          </div>
        `;
        
        container.appendChild(botCard);
      });
      
      lucide.createIcons();
    }

    // Initialize charts
    function initCharts() {
      // Revenue Chart
      const revenueChart = document.getElementById('revenueChart');
      if (!revenueChart) return;

      const revenueCtx = revenueChart.getContext('2d');
      charts.revenue = new Chart(revenueCtx, {
        type: 'line',
        data: {
          labels: ['Dec 1', 'Dec 5', 'Dec 10', 'Dec 15', 'Dec 20', 'Dec 25', 'Dec 30'],
          datasets: [{
            label: 'MRR',
            data: [1800, 1950, 2100, 2200, 2300, 2400, 2450],
            borderColor: '#A3E635',
            backgroundColor: 'rgba(163, 230, 53, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#A3E635',
            pointBorderColor: '#0A0E17',
            pointBorderWidth: 2,
            pointRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(26, 31, 46, 0.9)',
              titleColor: '#FFFFFF',
              bodyColor: '#9CA3AF',
              borderColor: '#374151',
              borderWidth: 1,
              cornerRadius: 6
            }
          },
          scales: {
            x: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { color: '#9CA3AF' }
            },
            y: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { 
                color: '#9CA3AF',
                callback: function(value) {
                  return '$' + value;
                }
              }
            }
          }
        }
      });

      // User Growth Chart
      const userGrowthChart = document.getElementById('userGrowthChart');
      if (!userGrowthChart) return;

      const userGrowthCtx = userGrowthChart.getContext('2d');
      charts.userGrowth = new Chart(userGrowthCtx, {
        type: 'line',
        data: {
          labels: ['Dec 1', 'Dec 5', 'Dec 10', 'Dec 15', 'Dec 20', 'Dec 25', 'Dec 30'],
          datasets: [
            {
              label: 'Manager Users',
              data: [120, 125, 130, 135, 140, 145, 150],
              borderColor: '#00D9E9',
              backgroundColor: 'rgba(0, 217, 233, 0.1)',
              fill: true,
              tension: 0.4
            },
            {
              label: 'Shot Users',
              data: [40, 45, 50, 55, 60, 65, 68],
              borderColor: '#A3E635',
              backgroundColor: 'rgba(163, 230, 53, 0.1)',
              fill: true,
              tension: 0.4
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'top',
              labels: {
                color: '#9CA3AF',
                usePointStyle: true,
                pointStyle: 'circle'
              }
            },
            tooltip: {
              backgroundColor: 'rgba(26, 31, 46, 0.9)',
              titleColor: '#FFFFFF',
              bodyColor: '#9CA3AF',
              borderColor: '#374151',
              borderWidth: 1,
              cornerRadius: 6
            }
          },
          scales: {
            x: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { color: '#9CA3AF' }
            },
            y: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { color: '#9CA3AF' }
            }
          }
        }
      });
    }

    // Load analytics
    function loadAnalytics() {
      // Revenue Overview Chart
      const revenueOverviewCtx = document.getElementById('revenueOverviewChart').getContext('2d');
      if (charts.revenueOverview) {
        charts.revenueOverview.destroy();
      }
      
      charts.revenueOverview = new Chart(revenueOverviewCtx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
          datasets: [{
            label: 'MRR',
            data: [1200, 1350, 1450, 1600, 1750, 1850, 1950, 2100, 2200, 2300, 2400, 2450],
            borderColor: '#A3E635',
            backgroundColor: 'rgba(163, 230, 53, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(26, 31, 46, 0.9)',
              titleColor: '#FFFFFF',
              bodyColor: '#9CA3AF',
              borderColor: '#374151',
              borderWidth: 1,
              cornerRadius: 6,
              callbacks: {
                label: function(context) {
                  return 'MRR: $' + context.parsed.y;
                }
              }
            }
          },
          scales: {
            x: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { color: '#9CA3AF' }
            },
            y: {
              grid: { color: '#374151', drawBorder: false },
              ticks: { 
                color: '#9CA3AF',
                callback: function(value) {
                  return '$' + value;
                }
              }
            }
          }
        }
      });

      // New Users Chart
      const newUsersCtx = document.getElementById('newUsersChart').getContext('2d');
      charts.newUsers = new Chart(newUsersCtx, {
        type: 'bar',
        data: {
          labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
          datasets: [{
            label: 'New Users',
            data: [12, 19, 8, 15, 10, 5, 14],
            backgroundColor: '#00D9E9',
            borderRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: '#9CA3AF' }
            },
            y: {
              grid: { color: '#374151' },
              ticks: { color: '#9CA3AF' }
            }
          }
        }
      });

      // Users by Status Chart
      const usersByStatusCtx = document.getElementById('usersByStatusChart').getContext('2d');
      charts.usersByStatus = new Chart(usersByStatusCtx, {
        type: 'doughnut',
        data: {
          labels: ['Paid', 'Trial', 'Free'],
          datasets: [{
            data: [45, 23, 88],
            backgroundColor: ['#A3E635', '#F59E0B', '#6B7280'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                color: '#9CA3AF',
                padding: 20
              }
            }
          }
        }
      });

      // Load top performers
      const topPerformersTbody = document.getElementById('topPerformersTableBody');
      if (topPerformersTbody) {
        topPerformersTbody.innerHTML = '';
        
        const performers = [
          { rank: '🥇', user: '@diamond_hands', trades: 98, winRate: 78.5, pnl: 3420 },
          { rank: '🥈', user: '@crypto_jane', trades: 56, winRate: 72.3, pnl: 1205 },
          { rank: '🥉', user: '@trader_mike', trades: 23, winRate: 68.2, pnl: 234 },
          { rank: '4', user: '@wolf_signals', trades: 12, winRate: 42.0, pnl: -89 },
          { rank: '5', user: '@crypto_pro', trades: 45, winRate: 65.8, pnl: 876 }
        ];
        
        performers.forEach(performer => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${performer.rank}</td>
            <td><strong>${performer.user}</strong></td>
            <td style="text-align: center;">${performer.trades}</td>
            <td style="text-align: center;">
              <span class="winrate-badge ${performer.winRate >= 65 ? 'good' : performer.winRate >= 50 ? 'medium' : 'bad'}">
                ${performer.winRate}%
              </span>
            </td>
            <td style="text-align: right;">
              <span class="pnl-cell ${performer.pnl >= 0 ? 'positive' : 'negative'}">
                ${performer.pnl >= 0 ? '+' : ''}$${Math.abs(performer.pnl).toFixed(2)}
              </span>
            </td>
          `;
          topPerformersTbody.appendChild(row);
        });
      }
      
      lucide.createIcons();
    }

    // Setup event listeners
    function setupEventListeners() {
      // Global search (only if element exists)
      const globalSearch = document.getElementById('globalSearch');
      if (globalSearch) {
        globalSearch.addEventListener('input', function(e) {
          const term = e.target.value.toLowerCase();
          // In a real app, this would filter the current page content
          console.log('Searching for:', term);
        });
      }

      // User modal mode toggle (only if element exists)
      const modalModeToggle = document.getElementById('modalModeToggle');
      if (modalModeToggle) {
        modalModeToggle.addEventListener('change', function(e) {
          const label = document.getElementById('modalModeLabel');
          if (label) {
            if (e.target.checked) {
              label.textContent = 'Active ⚡';
              label.style.color = 'var(--lime)';
            } else {
              label.textContent = 'Passive 👆';
              label.style.color = 'var(--cyan)';
            }
          }
        });
      }

      // Close modals on overlay click
      document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
          if (e.target === this) {
            this.classList.remove('active');
          }
        });
      });

      // Close modals on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
          });
          const notificationsPanel = document.getElementById('notificationsPanel');
          if (notificationsPanel) {
            notificationsPanel.classList.remove('active');
          }
        }
      });
    }

    // Open user modal
    function openUserModal(userId) {
      const user = users.find(u => u.id === userId);
      if (!user) return;
      
      // Set basic info
      document.getElementById('modalUserAvatar').textContent = user.avatar;
      document.getElementById('modalUserAvatar').style.background = user.avatarGradient;
      document.getElementById('modalUsername').innerHTML = `
        ${user.username ? '@' + user.username.replace('@', '') : user.chatId}
        <span class="badge badge-${user.managerStatus}" id="modalManagerBadge">${user.managerStatus === 'paid' ? 'Paid' : user.managerStatus === 'trial' ? 'Trial' : 'Free'}</span>
        <span class="badge badge-${user.shotStatus || 'free'}" id="modalShotBadge">${user.shotStatus === 'paid' ? 'Paid' : user.shotStatus === 'trial' ? 'Trial' : 'None'}</span>
        <span class="badge badge-${user.mode === 'active' ? 'active' : 'info'}" id="modalModeBadge">${user.mode === 'active' ? 'Active ⚡' : 'Passive 👆'}</span>
      `;
      document.getElementById('modalChatId').textContent = user.chatId;
      
      // Set stats
      document.getElementById('modalSignals').textContent = user.signals;
      document.getElementById('modalTrades').textContent = user.trades;
      document.getElementById('modalWinRate').textContent = user.winRate + '%';
      document.getElementById('modalPnl').textContent = (user.pnl > 0 ? '+' : '') + user.pnl.toFixed(2);
      
      // Set SignalManager info
      document.getElementById('modalManagerStatus').textContent = user.managerStatus === 'paid' ? 'Paid' : user.managerStatus === 'trial' ? 'Trial' : 'Free';
      document.getElementById('modalManagerStatus').className = `badge badge-${user.managerStatus}`;
      document.getElementById('modalManagerStart').textContent = user.managerStart;
      document.getElementById('modalManagerEnd').textContent = user.managerEnd || '—';
      document.getElementById('modalSignalsUsed').textContent = user.signals;
      
      // Set SignalShot info
      if (user.shotStatus) {
        document.getElementById('modalShotStatus').textContent = user.shotStatus === 'paid' ? 'Paid' : 'Trial';
        document.getElementById('modalShotStatus').className = `badge badge-${user.shotStatus}`;
        document.getElementById('modalShotStart').textContent = user.shotStart;
        document.getElementById('modalShotEnd').textContent = user.shotEnd;
        document.getElementById('modalTradesExec').textContent = user.trades;
      } else {
        document.getElementById('modalShotStatus').textContent = 'None';
        document.getElementById('modalShotStatus').className = 'badge badge-free';
        document.getElementById('modalShotStart').textContent = '—';
        document.getElementById('modalShotEnd').textContent = '—';
        document.getElementById('modalTradesExec').textContent = '—';
      }
      
      // Set trading settings
      const modeToggle = document.getElementById('modalModeToggle');
      modeToggle.checked = user.mode === 'active';
      modeToggle.dispatchEvent(new Event('change'));
      
      document.getElementById('modalStrategy').value = user.strategy || 'Close at TP2';
      document.getElementById('modalLeverage').value = user.leverage || '20X';
      document.getElementById('modalRiskPercent').value = user.riskPercent || 2;
      document.getElementById('modalMaxRisk').value = user.maxRisk || 50;
      document.getElementById('modalBaseBalance').value = user.baseBalance || 1000;
      
      // Open modal
      document.getElementById('userModalOverlay').classList.add('active');
      
      lucide.createIcons();
    }

    // Close user modal
    function closeUserModal() {
      document.getElementById('userModalOverlay').classList.remove('active');
    }

    // Open bot modal
    function openBotModal(bot) {
      document.getElementById('botModalName').innerHTML = `
        ${bot.name}
        <span class="bot-status-badge ${bot.status}">${bot.status === 'healthy' ? 'Healthy' : 'Warning'}</span>
      `;
      document.getElementById('botModalToken').textContent = `Token: ${bot.token}`;
      document.getElementById('botModalStatus').textContent = bot.status === 'healthy' ? 'Healthy' : 'Warning';
      document.getElementById('botModalStatus').className = `bot-status-badge ${bot.status}`;
      
      document.getElementById('botModalOverlay').classList.add('active');
      
      lucide.createIcons();
    }

    // Close bot modal
    function closeBotModal() {
      document.getElementById('botModalOverlay').classList.remove('active');
    }

    // Toggle notifications panel
    function toggleNotifications() {
      const notificationsPanel = document.getElementById('notificationsPanel');
      if (notificationsPanel) {
        notificationsPanel.classList.toggle('active');
      }
    }

    // Mark all notifications as read
    function markAllAsRead() {
      notifications.forEach(n => n.unread = false);
      loadNotifications();
    }

    // Refresh data
    function refreshData() {
      // Show loading state
      const refreshBtn = document.querySelector('.refresh-btn');
      const icon = refreshBtn.querySelector('i');
      const originalIcon = icon.getAttribute('data-lucide');
      
      icon.setAttribute('data-lucide', 'loader-2');
      icon.style.animation = 'spin 1s linear infinite';
      
      lucide.createIcons();
      
      // Simulate API call
      setTimeout(() => {
        icon.setAttribute('data-lucide', originalIcon);
        icon.style.animation = '';
        
        // Reload current page data
        switch(currentPage) {
          case 'dashboard':
            loadDashboard();
            break;
          case 'users-all':
            loadFullUsersTable();
            break;
          case 'trades':
            loadTrades();
            break;
          case 'analytics':
            loadAnalytics();
            break;
          case 'logs':
            loadSystemLogs();
            break;
        }
        
        lucide.createIcons();
        
        // Show success message
        showToast('Data refreshed successfully', 'success');
      }, 1000);
    }

    // Show toast notification
    function showToast(message, type = 'info') {
      // Create toast container if it doesn't exist
      let container = document.getElementById('toastContainer');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.position = 'fixed';
        container.style.bottom = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
      }
      
      // Create toast
      const toast = document.createElement('div');
      toast.className = 'toast';
      toast.style.background = type === 'success' ? 'var(--bg-card)' : 
                              type === 'error' ? 'var(--bg-card)' : 'var(--bg-card)';
      toast.style.color = 'var(--text-white)';
      toast.style.padding = '12px 16px';
      toast.style.borderRadius = '8px';
      toast.style.border = `1px solid ${type === 'success' ? 'var(--green)' : 
                                           type === 'error' ? 'var(--red)' : 'var(--cyan)'}`;
      toast.style.marginTop = '10px';
      toast.style.display = 'flex';
      toast.style.alignItems = 'center';
      toast.style.gap = '10px';
      toast.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.3)';
      toast.style.animation = 'slideIn 0.3s ease';
      
      // Add icon
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 
                                       type === 'error' ? 'x-circle' : 'info');
      icon.style.color = type === 'success' ? 'var(--green)' : 
                         type === 'error' ? 'var(--red)' : 'var(--cyan)';
      
      // Add message
      const text = document.createElement('span');
      text.textContent = message;
      text.style.fontSize = '13px';
      
      toast.appendChild(icon);
      toast.appendChild(text);
      container.appendChild(toast);
      
      lucide.createIcons();
      
      // Remove toast after 3 seconds
      setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
          toast.remove();
        }, 300);
      }, 3000);
    }

    // Format number with commas
    function formatNumber(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Action functions
    function sendMessageToUser() {
      const username = document.getElementById('modalUsername').textContent.split('\n')[0];
      showToast(`Opening chat with ${username}`, 'info');
    }

    function editUser() {
      showToast('Edit user functionality', 'info');
    }

    function resetUserSettings() {
      if (confirm('Are you sure you want to reset all settings for this user?')) {
        showToast('User settings reset', 'success');
      }
    }

    function banUser() {
      const username = document.getElementById('modalUsername').textContent.split('\n')[0];
      if (confirm(`Are you sure you want to ban ${username}?`)) {
        showToast(`User ${username} has been banned`, 'success');
        closeUserModal();
      }
    }

    function copyBotToken() {
      navigator.clipboard.writeText('85682•••••••••');
      showToast('Token copied to clipboard', 'success');
    }

    function restartBot() {
      showToast('Restarting bot...', 'info');
    }

    function viewBotUsers() {
      showToast('Opening bot users list', 'info');
    }

    function viewBotLogs() {
      showToast('Opening bot logs', 'info');
    }

    function saveBotSettings() {
      showToast('Bot settings saved', 'success');
    }

    function messageSelectedUsers() {
      showToast(`Messaging ${selectedUsers.size} users`, 'info');
    }

    function exportSelectedUsers() {
      showToast(`Exporting ${selectedUsers.size} users`, 'info');
    }

    function banSelectedUsers() {
      if (confirm(`Are you sure you want to ban ${selectedUsers.size} users?`)) {
        showToast(`${selectedUsers.size} users banned`, 'success');
        selectedUsers.clear();
        updateBulkActionsBar();
        loadFullUsersTable();
      }
    }

    function exportUsersCSV() {
      showToast('Exporting all users to CSV', 'info');
    }

    function openAddUserModal() {
      showToast('Opening add user form', 'info');
    }

    function resetFilters() {
      document.getElementById('userSearch').value = '';
      document.getElementById('statusFilter').value = 'all';
      document.getElementById('shotFilter').value = 'all';
      document.getElementById('modeFilter').value = 'all';
      document.getElementById('botFilter').value = 'all';
      document.getElementById('strategyFilter').value = 'all';
      showToast('Filters reset', 'info');
    }

    function showHelp() {
      showToast('Opening help documentation', 'info');
    }

    function viewRawSignal(raw) {
      alert('Raw signal:\n\n' + raw);
    }

    function createParserRule(raw) {
      showToast('Creating parser rule for: ' + raw.substring(0, 50) + '...', 'info');
    }

    function viewTrade(tradeId) {
      showToast(`Viewing trade ${tradeId}`, 'info');
    }

    function closeTrade(tradeId) {
      if (confirm('Are you sure you want to close this trade?')) {
        showToast(`Trade ${tradeId} closed`, 'success');
      }
    }

    function disableProvider(providerId) {
      if (confirm('Are you sure you want to disable this provider?')) {
        showToast('Provider disabled', 'success');
      }
    }

    function openProviderModal(providerId) {
      showToast(`Opening provider ${providerId} details`, 'info');
    }

    function messageUser(userId) {
      showToast('Opening chat with user', 'info');
    }

    function editUserModal(userId) {
      showToast('Editing user', 'info');
    }

    function toggleUserDropdown(userId) {
      const dropdown = document.getElementById(`dropdown-${userId}`);
      dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
      
      // Close other dropdowns
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu.id !== `dropdown-${userId}`) {
          menu.style.display = 'none';
        }
      });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.style.display = 'none';
        });
      }
    });

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
