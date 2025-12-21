<?php
/**
 * Cashier POS System Page
 * Protected page - only cashier/staff can access
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Only allow cashier/staff and admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}
// Get cashier display name from DB (preferred) with session fallbacks
require_once '../../public/actions/auth/db_config.php';
$cashierName = '';
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("SELECT first_name, last_name, store_id FROM cashier WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $cashierName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $store_id = intval($row['store_id'] ?? 0);
        }
        $stmt->close();
    }
}
// Fallbacks if DB did not return a name
if (empty($cashierName)) {
    $fn = isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) : '';
    $ln = isset($_SESSION['last_name']) ? trim($_SESSION['last_name']) : '';
    if ($fn || $ln) {
        $cashierName = trim($fn . ' ' . $ln);
    } elseif (isset($_SESSION['username'])) {
        $cashierName = $_SESSION['username'];
    } else {
        $cashierName = 'Cashier';
    }
}

// Fetch products to populate the menu (show all products under categories)
$products = [];
$pstmt = $conn->prepare("SELECT product_id, product_name, product_price, product_size, product_temperature, product_points, image_path, product_category FROM product ORDER BY product_id DESC");
if ($pstmt) {
    $pstmt->execute();
    $pres = $pstmt->get_result();
    while ($prow = $pres->fetch_assoc()) {
        $products[] = $prow;
    }
    $pstmt->close();
}

// Group products by product_category (from DB) so the cashier dropdown shows real categories
$menuData = [];
$categories = [];
foreach ($products as $p) {
    // normalize category: use provided product_category or fallback to 'Uncategorized'
    $cat = isset($p['product_category']) ? trim($p['product_category']) : '';
    if ($cat === '') $cat = 'Uncategorized';
    if (!isset($menuData[$cat])) {
        $menuData[$cat] = [];
        $categories[] = $cat;
    }
    $menuData[$cat][] = $p;
}

$menuDataJson = json_encode($menuData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cashier POS</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">
    <link rel="stylesheet" href="../../public/assets/css/cashier-styles.css">
    <!-- Load jsQR library from CDN (fallback to local file can be added if needed) -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        // Menu data from database
        const menuData = <?php echo $menuDataJson; ?>;
        // Cashier's store id from server (populated earlier in this PHP file)
        const cashierStoreId = <?php echo isset($store_id) ? (int)$store_id : 'null'; ?>;
        const allCategories = Object.keys(menuData);

        let cartItems = [];
        let qrStream = null;
        let qrScanning = false;

        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-menu-btn');
            const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
            const sidebar = document.querySelector('.sidebar');

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('active');
                });
            }

            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.remove('active');
                });
            }

            window.addEventListener('resize', function() {
                if(window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });

            const logoutBtn = document.querySelector('.admin-profile');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = '../../public/actions/auth/logout.php';
                    }
                });
            }

            // Render menu and setup UI
            // default to 'all' so the dropdown shows All and all items are displayed initially
            const firstCategory = 'all';
            renderMenu(firstCategory);
            setupCategoryDropdown();
            setupCheckoutEvents();
            setupQRScanner();

            const discountDropdown = document.getElementById('discount-dropdown');
            const cashInput = document.getElementById('cash-input');
            if (discountDropdown) discountDropdown.addEventListener('change', calculateTotals);
            if (cashInput) cashInput.addEventListener('input', calculateTotals);
        });

        /* ---------- MENU / CART ---------- */
        function renderMenu(category) {
            const menuGrid = document.getElementById('menu-grid');
            menuGrid.innerHTML = '';

            // If category is 'all', flatten all items from every category
            let items = [];
            if (category === 'all') {
                Object.keys(menuData).forEach(k => { items = items.concat(menuData[k] || []); });
            } else {
                items = menuData[category] || [];
            }
            items.forEach(item => {
                const itemCard = document.createElement('div');
                itemCard.className = 'menu-item';
                itemCard.innerHTML = `
                    <img src="${item.image_path ? item.image_path : '../../public/assets/coffee-1.jpg'}" alt="${item.product_name}" class="menu-item-image">
                    <div class="menu-item-name">${item.product_name}</div>
                    <div class="menu-item-prices">
                        <div class="menu-item-price-item"><span>₱${parseFloat(item.product_price).toFixed(2)}</span></div>
                    </div>
                    <div class="menu-item-points">Pts: ${item.product_points || 0}</div>
                    <button class="add-btn" data-item='${JSON.stringify(item)}'>+ Add</button>
                `;
                menuGrid.appendChild(itemCard);
            });

            setupAddToCartButtons();
        }

        function setupCategoryDropdown() {
            const dropdown = document.querySelector('.category-dropdown select');
                if (dropdown) {
                // start with an 'All' option so users can view all items
                dropdown.innerHTML = '';
                const allOpt = document.createElement('option');
                allOpt.value = 'all';
                allOpt.textContent = 'All';
                dropdown.appendChild(allOpt);
                allCategories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
                    dropdown.appendChild(option);
                });
                // ensure the default selection is 'All'
                dropdown.selectedIndex = 0;

                const labelSpan = document.querySelector('.category-dropdown > span');
                const arrowSpan = document.querySelector('.category-dropdown .dropdown-arrow');
                // initialize label to match selected option
                if (labelSpan) labelSpan.textContent = dropdown.options[dropdown.selectedIndex].textContent;

                dropdown.addEventListener('change', function(e) {
                    renderMenu(e.target.value);
                    // update only the visible label, keep the <select> element intact so it remains selectable
                    if (labelSpan) labelSpan.textContent = this.options[this.selectedIndex].textContent;
                    if (arrowSpan) arrowSpan.textContent = '▼';
                });
            }
        }

        function setupAddToCartButtons() {
            const menuGrid = document.getElementById('menu-grid');
            if (!menuGrid) return;

            if (menuGrid._addToCartHandler) {
                menuGrid.removeEventListener('click', menuGrid._addToCartHandler);
            }
            menuGrid._addToCartHandler = function(e) {
                const btn = e.target.closest('.add-btn');
                if (!btn || !menuGrid.contains(btn)) return;
                const itemData = btn.getAttribute('data-item');
                try {
                    const item = JSON.parse(itemData);
                    addToCart(item);
                } catch (err) {
                    console.error('Invalid item data', err);
                }
            };
            menuGrid.addEventListener('click', menuGrid._addToCartHandler);
        }

        function addToCart(item) {
            // Check if free refill is selected
            const discountDropdown = document.getElementById('discount-dropdown');
            let isFreeRefill = false;
            if (discountDropdown && discountDropdown.value) {
                const selectedOpt = discountDropdown.options[discountDropdown.selectedIndex];
                if (selectedOpt && selectedOpt.dataset) {
                    const rewardName = (selectedOpt.dataset.name || '').toString();
                    if (rewardName.toLowerCase().includes('free refill') || rewardName.toLowerCase().includes('refill')) {
                        isFreeRefill = true;
                    }
                }
            }

            // If free refill is active, restrict to only 1 item total
            if (isFreeRefill) {
                if (cartItems.length > 0) {
                    alert('Free Refill can only have 1 item. Please remove existing items first.');
                    return;
                }
                cartItems.push({...item, quantity: 1, price: parseFloat(item.product_price)});
            } else {
                // Normal flow
                const existing = cartItems.find(i => i.product_id === item.product_id);
                if (existing) {
                    existing.quantity++;
                } else {
                    cartItems.push({...item, quantity: 1, price: parseFloat(item.product_price)});
                }
            }
            updateCheckout();
        }

        function removeFromCart(productId) {
            cartItems = cartItems.filter(i => i.product_id !== productId);
            updateCheckout();
        }

        function updateQuantity(productId, change) {
            // Check if free refill is selected
            const discountDropdown = document.getElementById('discount-dropdown');
            let isFreeRefill = false;
            if (discountDropdown && discountDropdown.value) {
                const selectedOpt = discountDropdown.options[discountDropdown.selectedIndex];
                if (selectedOpt && selectedOpt.dataset) {
                    const rewardName = (selectedOpt.dataset.name || '').toString();
                    if (rewardName.toLowerCase().includes('free refill') || rewardName.toLowerCase().includes('refill')) {
                        isFreeRefill = true;
                    }
                }
            }

            const item = cartItems.find(i => i.product_id === productId);
            if (item) {
                // Prevent quantity increase beyond 1 if free refill is active
                if (isFreeRefill && change > 0 && item.quantity >= 1) {
                    alert('Free Refill can only have 1 item.');
                    return;
                }
                item.quantity += change;
                if (item.quantity <= 0) removeFromCart(productId);
                else updateCheckout();
            }
        }

        function updateCheckout() {
            const itemsHtml = cartItems.map(item => `
                <div class="checkout-item">
                    <span class="item-name">${item.product_name}</span>
                    <div class="item-qty-controls">
                        <button class="qty-btn" onclick="updateQuantity(${item.product_id}, -1)">-</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQuantity(${item.product_id}, 1)">+</button>
                    </div>
                    <span class="item-price">₱${(item.price * item.quantity).toFixed(2)}</span>
                    <span class="remove-btn" onclick="removeFromCart(${item.product_id})">✕</span>
                </div>
            `).join('');

            document.getElementById('checkout-items').innerHTML = itemsHtml || '<div style="text-align: center; color: #999; padding: 30px 0;">No items added</div>';
            // Update points to earn (sum of product_points * qty)
            try {
                const pts = cartItems.reduce((sum, it) => sum + ((parseInt(it.product_points) || 0) * (parseInt(it.quantity) || 0)), 0);
                const ptsInput = document.getElementById('points-to-earn');
                if (ptsInput) ptsInput.value = pts;
            } catch (e) {
                console.warn('Failed to compute points', e);
            }

            calculateTotals();
        }

        function calculateTotals() {
            const discountDropdown = document.getElementById('discount-dropdown');
            let discountPercent = 0;
            let isFreeRefill = false;

            console.log('=== calculateTotals called ===');
            console.log('Discount dropdown value:', discountDropdown ? discountDropdown.value : 'null');

            // Check if free refill is selected FIRST
            if (discountDropdown && discountDropdown.value) {
                const selectedOpt = discountDropdown.options[discountDropdown.selectedIndex];
                console.log('Selected option:', selectedOpt);
                console.log('Selected option dataset:', selectedOpt ? selectedOpt.dataset : 'null');
                
                if (selectedOpt && selectedOpt.dataset) {
                    // Check if this is a Free Refill reward (reward name contains "Free Refill")
                    const rewardName = (selectedOpt.dataset.name || '').toString();
                    const rewardPoints = (selectedOpt.dataset.points || '').toString();
                    
                    console.log('Reward name:', rewardName);
                    console.log('Reward points:', rewardPoints);
                    console.log('Name includes "free refill":', rewardName.toLowerCase().includes('free refill'));
                    console.log('Name includes "refill":', rewardName.toLowerCase().includes('refill'));
                    
                    if (rewardName.toLowerCase().includes('free refill') || rewardName.toLowerCase().includes('refill')) {
                        isFreeRefill = true;
                        console.log('FREE REFILL DETECTED!');
                    }
                }
            }

            console.log('Is free refill?:', isFreeRefill);

            // If free refill, set all totals to 0
            if (isFreeRefill) {
                console.log('Setting all totals to 0.00');
                document.getElementById('subtotal').textContent = '0.00';
                document.getElementById('discount').textContent = '0.00';
                document.getElementById('total').textContent = '0.00';
            } else {
                // Normal calculation
                const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                console.log('Subtotal (normal):', subtotal);

                if (discountDropdown && discountDropdown.value) {
                    const selectedOpt = discountDropdown.options[discountDropdown.selectedIndex];
                    if (selectedOpt && selectedOpt.dataset) {
                        if (selectedOpt.dataset.percent) {
                            discountPercent = parseFloat(selectedOpt.dataset.percent) || 0;
                        } else if (selectedOpt.dataset.type === 'Discount Voucher') {
                            const name = (selectedOpt.dataset.name || '').toString();
                            const m = name.match(/(\d+)%/);
                            if (m) discountPercent = parseInt(m[1]);
                        }
                    }
                }

                const discountAmount = subtotal * (discountPercent / 100);
                const total = subtotal - discountAmount;

                document.getElementById('subtotal').textContent = subtotal.toFixed(2);
                document.getElementById('discount').textContent = discountAmount.toFixed(2);
                document.getElementById('total').textContent = total.toFixed(2);
            }

            // Hide/show payment section and cash input based on whether it's a free refill
            const paymentSection = document.querySelector('.payment-section');
            const cashPaymentRow = document.querySelector('.total-row.payment');
            const changeRow = document.querySelector('.total-row.change');
            const cashInput = document.getElementById('cash-input');
            
            if (isFreeRefill) {
                console.log('Hiding payment UI');
                if (paymentSection) paymentSection.style.display = 'none';
                if (cashPaymentRow) cashPaymentRow.style.display = 'none';
                if (changeRow) changeRow.style.display = 'none';
                if (cashInput) cashInput.value = '';
            } else {
                console.log('Showing payment UI');
                if (paymentSection) paymentSection.style.display = '';
                if (cashPaymentRow) cashPaymentRow.style.display = '';
                if (changeRow) changeRow.style.display = '';
                
                if (cashInput) {
                    const total = parseFloat(document.getElementById('total').textContent);
                    const cash = parseFloat(cashInput.value || 0);
                    const change = (isNaN(cash) ? 0 : cash) - total;
                    document.getElementById('change').textContent = (isNaN(change) ? 0 : change).toFixed(2);
                }
            }
        }

        /* ---------- DISCOUNT POPULATION ---------- */
        function populateDiscountDropdown(rewards) {
            const dd = document.getElementById('discount-dropdown');
            if (!dd) return;
            dd.innerHTML = '';

            // Default option
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '-- Select Discount --';
            dd.appendChild(defaultOpt);

            if (Array.isArray(rewards) && rewards.length > 0) {
                rewards.forEach(r => {
                    console.log('Populating reward:', r);
                    const opt = document.createElement('option');
                    opt.value = 'reward_' + r.reward_id;
                    opt.textContent = r.reward_name || r.reward_type || 'Reward';
                    opt.dataset.type = r.reward_type || '';
                    opt.dataset.name = r.reward_name || '';
                    opt.dataset.points = r.points || '';
                    console.log('Setting dataset.points to:', r.points, 'stringified:', String(r.points));
                    if (typeof r.discount_percent !== 'undefined' && r.discount_percent !== null && parseInt(r.discount_percent)) {
                        opt.dataset.percent = parseInt(r.discount_percent);
                    }
                    opt.dataset.start = r.start_date || '';
                    opt.dataset.expiration = r.expiration_date || '';
                    opt.dataset.rewardId = r.reward_id;
                    dd.appendChild(opt);
                });
            }

            // Also include system static percentage discounts as fallback
            const sys = [5,10,15,20];
            sys.forEach(p => {
                const opt = document.createElement('option');
                opt.value = 'sys_' + p;
                opt.textContent = p + '% Discount';
                opt.dataset.percent = p;
                dd.appendChild(opt);
            });

            dd.addEventListener('change', calculateTotals);
        }

        /* ---------- QR / MEMBER ---------- */
        function setupQRScanner() {
            const scanBtn = document.getElementById('scan-btn');
            const qrModal = document.getElementById('qr-modal');
            const qrCloseBtn = document.getElementById('qr-close-btn');
            const qrStartBtn = document.getElementById('qr-start-btn');
            const qrCloseModalBtn = document.getElementById('qr-close-modal-btn');

            if (!scanBtn) return;
            scanBtn.addEventListener('click', function() { qrModal.classList.add('active'); });
            qrCloseBtn.addEventListener('click', function() { stopQRScanner(); qrModal.classList.remove('active'); });
            qrCloseModalBtn.addEventListener('click', function() { stopQRScanner(); qrModal.classList.remove('active'); });
            qrStartBtn.addEventListener('click', function() { startQRScanner(); });
            qrModal.addEventListener('click', function(e) { if (e.target === qrModal) { stopQRScanner(); qrModal.classList.remove('active'); } });
        }

        function startQRScanner() {
            const video = document.getElementById('qr-video');
            const startBtn = document.getElementById('qr-start-btn');
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera not supported by this browser.');
                return;
            }

            // Many mobile browsers require a secure context (HTTPS) to access the camera.
            if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                alert('Camera access requires a secure origin (HTTPS). For mobile testing, run the site over HTTPS or use a tunnel like ngrok.');
                return;
            }

            startBtn.disabled = true; startBtn.textContent = 'Starting camera...';

            // Try progressive constraints to increase compatibility across devices/browsers
            const constraintsList = [
                { video: { facingMode: { ideal: 'environment' } } },
                { video: { facingMode: 'environment' } },
                { video: true }
            ];

            let attempt = 0;
            function tryNextConstraint() {
                if (attempt >= constraintsList.length) {
                    startBtn.disabled = false; startBtn.textContent = 'Start Scanning';
                    alert('Unable to access the camera on this device.');
                    return;
                }
                const c = constraintsList[attempt++];
                navigator.mediaDevices.getUserMedia(c).then(function(stream) {
                    qrStream = stream; video.srcObject = stream; video.play(); qrScanning = true; startBtn.textContent = 'Scanning...';
                    scanQRCode();
                }).catch(function(err) {
                    console.warn('getUserMedia failed with constraints', c, err);
                    // If the user denied permission, don't keep trying other constraints
                    if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
                        startBtn.disabled = false; startBtn.textContent = 'Start Scanning';
                        alert('Camera permission denied. Please allow camera access in your browser settings.');
                        return;
                    }
                    // Try the next constraint option
                    tryNextConstraint();
                });
            }

            tryNextConstraint();
        }

        function stopQRScanner() {
            qrScanning = false;
            if (qrStream) { qrStream.getTracks().forEach(t => t.stop()); qrStream = null; }
            const startBtn = document.getElementById('qr-start-btn'); if (startBtn) { startBtn.disabled = false; startBtn.textContent = 'Start Scanning'; }
        }

        function scanQRCode() {
            const video = document.getElementById('qr-video');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const scanInterval = setInterval(function() {
                if (!qrScanning) { clearInterval(scanInterval); return; }
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    if (code) { handleQRResult(code.data); clearInterval(scanInterval); stopQRScanner(); }
                }
            }, 150);
        }

        function handleQRResult(data) {
            const resultDiv = document.getElementById('qr-result');
            const resultValue = document.getElementById('qr-result-value');
            resultValue.textContent = data; resultDiv.classList.add('active');
            const parts = data.split('|');
            if (parts.length === 0) return;
            const memberId = parts[0];
            const memberIdInput = document.querySelector('input[placeholder="Enter ID"]'); if (memberIdInput) memberIdInput.value = memberId;
            if (parts.length > 1) { const memberNameInput = document.querySelector('input[placeholder="Name"]'); if (memberNameInput) memberNameInput.value = parts[1]; }

            // fetch rewards for this customer
            fetch('../../public/actions/customer/get_rewards.php', { method: 'POST', body: new URLSearchParams({ customer_id: memberId }) })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    populateDiscountDropdown(json.rewards);
                    displayMemberRewards(json.rewards);
                } else {
                    populateDiscountDropdown([]);
                    displayMemberRewards([]);
                }
            })
            .catch(err => { console.error('Error fetching rewards', err); populateDiscountDropdown([]); displayMemberRewards([]); });
        }

        function displayMemberRewards(rewards) {
            const container = document.getElementById('member-rewards-list');
            const dd = document.getElementById('discount-dropdown');
            if (!container) return;
            if (!Array.isArray(rewards) || rewards.length === 0) {
                container.innerHTML = '<span style="color:#666;">No active rewards for this member.</span>';
                return;
            }
            // Build list
            let html = '<ul style="padding-left:16px;margin:0;font-size:13px;color:#333;">';
            rewards.forEach(r => {
                const name = r.reward_name || r.reward_type || 'Reward';
                const exp = r.expiration_date ? ('Expires: ' + r.expiration_date) : '';
                html += '<li style="margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;">'
                    + '<span style="flex:1;">' + escapeHtml(name) + (exp ? ' <span style="color:#888;font-size:12px;">(' + escapeHtml(exp) + ')</span>' : '') + '</span>'
                    + '<button type="button" class="use-reward-btn" data-reward-id="' + (r.reward_id || '') + '" style="margin-left:8px;padding:4px 8px;border-radius:4px;border:1px solid #6b4423;background:#fff;color:#6b4423;cursor:pointer;">Use</button>'
                    + '</li>';
            });
            html += '</ul>';
            container.innerHTML = html;

            // Attach click handlers to 'Use' buttons to select the reward in the discount dropdown
            container.querySelectorAll('.use-reward-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const rid = this.dataset.rewardId;
                    if (!rid) return;
                    // find option in dropdown that matches reward_<id>
                    if (dd) {
                        let found = false;
                        for (let i=0;i<dd.options.length;i++) {
                            const opt = dd.options[i];
                            if (opt.value === 'reward_' + rid) { dd.selectedIndex = i; found = true; break; }
                        }
                        if (!found) {
                            // if not present, append it then select
                            const opt = document.createElement('option');
                            opt.value = 'reward_' + rid;
                            opt.textContent = 'Reward ' + rid;
                            opt.dataset.rewardId = rid;
                            dd.appendChild(opt);
                            dd.value = opt.value;
                        }
                        // trigger change to recalc totals
                        const evt = new Event('change'); dd.dispatchEvent(evt);
                    }
                });
            });
        }

        function clearMemberRewardsDisplay() {
            const container = document.getElementById('member-rewards-list');
            if (container) container.innerHTML = 'No member selected.';
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; });
        }

        /* ---------- CHECKOUT / ORDER ---------- */
        function setupCheckoutEvents() {
            const cancelBtn = document.getElementById('cancel-order-btn');
            const orderBtn = document.getElementById('order-btn');
            if (cancelBtn) cancelBtn.addEventListener('click', function() { if (confirm('Clear all items?')) { cartItems = []; updateCheckout(); } });
            if (orderBtn) orderBtn.addEventListener('click', function() {
                if (cartItems.length === 0) { alert('Please add items to proceed'); return; }
                // Build payload
                const memberId = (document.querySelector('input[placeholder="Enter ID"]')||{}).value || '';
                const dd = document.getElementById('discount-dropdown');
                let reward_id = null;
                let discountPercent = 0;
                let isFreeRefillVoucher = false;
                let paymentMethod = document.querySelector('input[name="payment"]:checked') ? document.querySelector('input[name="payment"]:checked').value : 'cash';

                if (dd && dd.value) {
                    if (dd.value.startsWith('reward_')) {
                        reward_id = parseInt(dd.value.replace('reward_',''), 10);
                    }
                    // extract discount percent for order calculation
                    const selectedOpt = dd.options[dd.selectedIndex];
                    if (selectedOpt && selectedOpt.dataset) {
                        if (selectedOpt.dataset.percent) {
                            discountPercent = parseFloat(selectedOpt.dataset.percent) || 0;
                        } else if (selectedOpt.dataset.type === 'Discount Voucher') {
                            const name = (selectedOpt.dataset.name || '').toString();
                            const m = name.match(/(\d+)%/);
                            if (m) discountPercent = parseInt(m[1]);
                        }
                        // Check if this is a Free Refill reward (reward name contains "Free Refill")
                        const rewardName = (selectedOpt.dataset.name || '').toString();
                        const rewardPoints = (selectedOpt.dataset.points || '').toString();
                        if (rewardName.toLowerCase().includes('free refill') || rewardName.toLowerCase().includes('refill')) {
                            isFreeRefillVoucher = true;
                            paymentMethod = 'none'; // Set payment method to 'none' for free refill
                        }
                    }
                }

                // Validate cash input when paying by cash: ensure amount provided and sufficient
                // Skip validation if it's a free refill voucher
                if (paymentMethod === 'cash') {
                    const totalEl = document.getElementById('total');
                    const total = parseFloat(totalEl ? totalEl.textContent : 0) || 0;
                    const cashInputEl = document.getElementById('cash-input');
                    const cashVal = cashInputEl ? parseFloat(cashInputEl.value) : NaN;
                    if (isNaN(cashVal)) {
                        alert('Please enter cash amount to proceed.');
                        return;
                    }
                    if (cashVal < total) {
                        alert('Not enough cash. Please enter sufficient amount.');
                        return;
                    }
                }

                const items = cartItems.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price }));

                fetch('../../public/actions/orders/save_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ items: items, customer_id: memberId || null, payment_method: paymentMethod, reward_id: reward_id, discount_percent: discountPercent, store_id: cashierStoreId })
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Order processed (ID: ' + (json.order_id || '-') + '). ' + (isFreeRefillVoucher ? 'Free Refill applied!' : 'Total: ₱' + document.getElementById('total').textContent));
                        // reset local UI state then reload to reflect persisted data
                        cartItems = [];
                        updateCheckout();
                        // stop any running QR scanner and clear member info
                        try { stopQRScanner(); } catch (e) { /* ignore */ }
                        const memberIdInput = document.querySelector('input[placeholder="Enter ID"]'); if (memberIdInput) memberIdInput.value = '';
                        const memberNameInput = document.querySelector('input[placeholder="Name"]'); if (memberNameInput) memberNameInput.value = '';
                        clearMemberRewardsDisplay();
                        // give the user a brief moment after the alert, then reload
                        setTimeout(function() { window.location.reload(); }, 500);
                    } else {
                        alert('Error processing order: ' + (json.message || 'Unknown'));
                    }
                })
                .catch(err => { console.error('Order save error', err); alert('Error processing order'); });
            });
        }

        

        
    </script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="cafe-logo">
                    <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="cashier.php" class="nav-link active ">
                    <span class="nav-icon">☕</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link ">
                    <span class="nav-icon">💳</span>
                    <span class="nav-text">Transactions</span>
                </a>
                
                <a href="inventory.php" class="nav-link ">
                    <span class="nav-icon">🥫</span>
                    <span class="nav-text">Ingredients</span>
                </a>

                  <a href="settings.php" class="nav-link">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Settings</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburger-menu-btn">☰</button>
                    <h1 class="page-title">POS System</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                                                <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Cashier Content -->
            <div class="cashier-container">
                <!-- Menu Section -->
                <div class="menu-section">
                    <div class="category-header">
                            <div class="category-dropdown">
                                <span>All</span>
                                <span class="dropdown-arrow">▼</span>
                                <select>
                                    <option value="all">All</option>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars(ucfirst($cat)); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                    </div>

                    <div class="menu-grid" id="menu-grid">
                        <!-- Menu items will be inserted here -->
                    </div>
                </div>

                <!-- Checkout Section -->
                <div class="checkout-section">
                    <div class="checkout-title">Checkout</div>

                    <div class="checkout-items" id="checkout-items">
                        <div style="text-align: center; color: #999; padding: 30px 0;">No items added</div>
                    </div>

                    <div class="checkout-divider"></div>

                    <!-- Payment Options -->
                    <div class="payment-section">
                        <label class="payment-label">Payment Method:</label>
                        <div class="payment-options">
                            <div class="payment-option">
                                <input type="radio" id="cash" name="payment" value="cash" checked>
                                <label for="cash">Cash</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="card" name="payment" value="card">
                                <label for="card">Card</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="online" name="payment" value="online">
                                <label for="online">Online Payment</label>
                            </div>
                        </div>
                    </div>

                    <!-- Loyalty Points -->
                    <div class="loyalty-section">
                        <label class="loyalty-label">Loyalty Points</label>
                        <div class="loyalty-field">
                            <label>Member ID</label>
                            <input type="text" id="member-id-input" placeholder="Enter ID">
                        </div>
                        <div class="loyalty-field">
                            <label>Member Name</label>
                            <input type="text" id="member-name-input" placeholder="Name" disabled>
                        </div>
                        <div class="loyalty-field">
                            <label>Points to Earn</label>
                            <input type="text" id="points-to-earn" value="0" disabled>
                        </div>
                    </div>

                    <!-- Discount -->
                    <div class="discount-section" style="margin-top:12px;">
                        <label style="font-weight:600;display:block;margin-bottom:6px;color:#333;">Discount</label>
                        <select id="discount-dropdown" style="width:100%;padding:6px;border-radius:4px;border:1px solid #ddd;background:#fff;">
                            <option value="">-- Select Discount --</option>
                        </select>
                    </div>

                    <!-- Member's available rewards/vouchers (populated after QR scan) -->
                    <div class="member-rewards" id="member-rewards" style="margin-top:12px;">
                        <label style="font-weight:600;display:block;margin-bottom:6px;color:#333;">Member Rewards / Vouchers</label>
                        <div id="member-rewards-list" style="min-height:28px;color:#666;">No member selected.</div>
                    </div>

                    <!-- Totals -->
                    <div class="totals-section">
                        <div class="total-row">
                            <span class="total-label">SubTotal</span>
                            <span class="total-value" id="subtotal"></span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Discounted Price</span>
                            <span class="total-value" id="discount">0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span class="total-label">Total</span>
                            <span class="total-value" id="total">0.00</span>
                        </div>
                       <div class="total-row payment">
    <span class="total-label">Cash</span>
    <input type="number" id="cash-input" value="" style="width: 80px;" min="0" step="0.01">

</div>

<div class="total-row change">
    <span class="total-label">Change</span>
    <span class="total-value" id="change">0.00</span>
</div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="action-btn scan-btn" id="scan-btn">Scan</button>
                        <button class="action-btn cancel-btn" id="cancel-order-btn">Cancel Order</button>
                        <button class="action-btn order-btn" id="order-btn">Order</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- QR Scanner Modal -->
    <div class="qr-modal" id="qr-modal">
        <div class="qr-modal-content">
            <div class="qr-modal-header">
                <h2 class="qr-modal-title">QR Code Scanner</h2>
                <button class="qr-close-btn" id="qr-close-btn">&times;</button>
            </div>

            <div class="qr-scanner-info">
                <strong>Position the QR code</strong> in front of your camera to scan customer details
            </div>

            <div class="qr-scanner-container">
                <video id="qr-video"></video>
            </div>

            <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                <button type="button" class="qr-btn" id="qr-upload-btn">Upload QR image</button>
                <input type="file" id="qr-file-input" accept="image/*" style="display:none;">
                <div id="qr-upload-status" style="font-size:13px;color:#666;"></div>
            </div>

            <div class="qr-result" id="qr-result">
                <div class="qr-result-label">✓ QR Code Scanned:</div>
                <div class="qr-result-value" id="qr-result-value"></div>
            </div>

            <div class="qr-modal-actions">
                <button class="qr-btn qr-btn-start" id="qr-start-btn">Start Scanning</button>
                <button class="qr-btn qr-btn-close" id="qr-close-modal-btn">Close</button>
            </div>
        </div>
    </div>
    <script>
        // Image upload scanning fallback for devices where camera access is restricted
        function scanImageFile(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const max = 1024;
                        let w = img.width, h = img.height;
                        if (w > max || h > max) {
                            const ratio = Math.min(max / w, max / h);
                            w = Math.round(w * ratio);
                            h = Math.round(h * ratio);
                        }
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        try {
                            const imageData = ctx.getImageData(0, 0, w, h);
                            const code = jsQR(imageData.data, imageData.width, imageData.height);
                            if (code && code.data) resolve(code.data); else reject(new Error('No QR code found in image'));
                        } catch (err) {
                            reject(err);
                        }
                    };
                    img.onerror = function() { reject(new Error('Failed to load image')); };
                    img.src = e.target.result;
                };
                reader.onerror = function() { reject(new Error('Failed to read file')); };
                reader.readAsDataURL(file);
            });
        }
        // wire upload UI in setupQRScanner
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                const uploadBtn = document.getElementById('qr-upload-btn');
                const fileInput = document.getElementById('qr-file-input');
                const status = document.getElementById('qr-upload-status');
                if (!uploadBtn || !fileInput) return;
                uploadBtn.addEventListener('click', function() { fileInput.click(); });
                fileInput.addEventListener('change', function(e) {
                    const f = (e.target.files && e.target.files[0]) || null;
                    if (!f) return;
                    status.textContent = 'Scanning image...';
                    scanImageFile(f).then(data => {
                        status.textContent = 'QR found';
                        handleQRResult(data);
                    }).catch(err => {
                        status.textContent = 'No QR found in image';
                        console.warn('Image scan failed', err);
                        alert('Could not find a QR code in the selected image.');
                    }).finally(() => { fileInput.value = ''; setTimeout(() => status.textContent = '', 2500); });
                });
            });
        })();
    </script>
</body>
</html>