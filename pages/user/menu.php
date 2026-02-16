<?php
session_start();
require_once '../../public/actions/auth/db_config.php';

// Fetch all products from database, grouped by category
$products = [];
$categories = [];

$stmt = $conn->prepare("SELECT DISTINCT product_category FROM product ORDER BY product_category ASC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row['product_category'];
    }
    $stmt->close();
}

// Fetch products for each category
foreach ($categories as $category) {
    $pstmt = $conn->prepare("SELECT product_id, product_name, product_price, product_points, image_path FROM product WHERE product_category = ? ORDER BY product_name ASC");
    if ($pstmt) {
        $pstmt->bind_param('s', $category);
        $pstmt->execute();
        $pres = $pstmt->get_result();
        $categoryProducts = [];
        while ($prow = $pres->fetch_assoc()) {
            // Use product image if available, otherwise use default coffee image
            $imagePath = !empty($prow['image_path']) ? $prow['image_path'] : '../../public/assets/images/Default.jpg';
            $categoryProducts[] = [
                'id' => (int)$prow['product_id'],
                'name' => htmlspecialchars($prow['product_name']),
                'price' => (float)$prow['product_price'],
                'points' => (int)($prow['product_points'] ?? 0),
                'image' => $imagePath
            ];
        }
        if (!empty($categoryProducts)) {
            $products[$category] = $categoryProducts;
        }
        $pstmt->close();
    }
}

$menu_cover_image = null;
$menu_cover_text = '';


if (isset($conn)) {
    $stmt = $conn->prepare("SELECT cover_image, cover_text FROM home_page_assets WHERE category ='Menu' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $menu_cover_image = !empty($row['cover_image']) ? $row['cover_image'] : null;
            $menu_cover_text = !empty($row['cover_text']) ? $row['cover_text'] : '';
        }
        $stmt->close();
    }
}

// Prepare categories and convert to JSON for JavaScript
$menuCategories = array_keys($products);
// Force 'Coffee' to be first if present
if (($pos = array_search('Coffee', $menuCategories, true)) !== false) {
    array_splice($menuCategories, $pos, 1);
    array_unshift($menuCategories, 'Coffee');
}

$productsJson = json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$categoriesJson = json_encode($menuCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$initialCategory = htmlspecialchars($menuCategories[0] ?? 'Coffee');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Cups & Stories Cafe</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=logout" />
    <style>
        .menu-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .mobile-controls {
            display: none;
        }

        .categories-sidebar {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .categories-sidebar h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .categories-sidebar ul {
            list-style: none;
        }

        .categories-sidebar li {
            padding: 12px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            color: #666;
            border-left: 3px solid transparent;
        }

        .categories-sidebar li:hover {
            background-color: #f5f1ed;
            color: #333;
        }

        .categories-sidebar li.active {
            background-color: #e8ddd0;
            color: #6b4423;
            border-left-color: #6b4423;
            font-weight: 600;
        }

        .products-section {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .category-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .product-card {
            background-color: #f9f7f4;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .product-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background-color: #e8ddd0;
        }

        .product-info {
            padding: 15px;
        }

        .product-name {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .product-points {
            font-size: 12px;
            color: #6b4423;
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        @media (max-width: 1024px) {
            .menu-container {
                gap: 20px;
                padding: 30px 20px;
                grid-template-columns: 200px 1fr;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .menu-container {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }

            /* On tablet and below, hide the sidebar by default and show a mobile control bar */
            .categories-sidebar {
                display: none;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                top: 120px;
                width: 90%;
                max-height: 60vh;
                overflow: auto;
                padding: 15px;
                z-index: 1200;
            }

            .categories-sidebar.mobile-open {
                display: block;
            }

            .mobile-controls {
                display: flex;
                gap: 10px;
                align-items: center;
                margin-bottom: 10px;
            }

            .category-toggle {
                padding: 10px 14px;
                background: #6b4423;
                color: #fff;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
            }

            /* hide header search; show mobile search */
            .header-center .search {
                display: none;
            }

            .mobile-search {
                display: block;
                width: 100%;
                padding: 10px 12px;
                border-radius: 6px;
                border: 1px solid #ddd;
            }

            .categories-sidebar h3 {
                grid-column: 1 / -1;
                margin-bottom: 10px;
                font-size: 16px;
            }

            .categories-sidebar ul {
                display: contents;
            }

            .categories-sidebar li {
                padding: 10px;
                font-size: 12px;
            }

            .products-section {
                padding: 20px;
            }

            .category-title {
                font-size: 22px;
                margin-bottom: 20px;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .menu-container {
                padding: 15px;
            }

            .products-section {
                padding: 15px;
            }

            .category-title {
                font-size: 18px;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .product-img {
                height: 120px;
            }

            .product-info {
                padding: 10px;
            }

            .product-name {
                font-size: 13px;
            }

            .product-points,
            .product-price {
                font-size: 11px;
            }
        }
    </style>

    <!-- QR generation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Products data from PHP/Database
        const productsData = <?php echo $productsJson; ?>;
        const allCategories = <?php echo $categoriesJson; ?>;

        // Utility: debounce
        function debounce(fn, wait) {
            let t;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        // Render helpers
        function renderProducts(category) {
            const grid = document.getElementById('product-grid');
            const title = document.getElementById('category-title');
            title.textContent = category;
            grid.innerHTML = '';

            const items = productsData[category] || [];
            items.forEach(product => {
                grid.appendChild(createProductCard(product));
            });

            // Update active class on sidebar
            document.querySelectorAll('.categories-sidebar li').forEach(li => {
                if (li.textContent.trim() === category) {
                    li.classList.add('active');
                } else {
                    li.classList.remove('active');
                }
            });
        }

        function renderProductsByList(productsArray, titleText) {
            const grid = document.getElementById('product-grid');
            const title = document.getElementById('category-title');
            title.textContent = titleText;
            grid.innerHTML = '';

            if (!productsArray || productsArray.length === 0) {
                const empty = document.createElement('div');
                empty.textContent = 'No items found.';
                empty.style.color = '#666';
                grid.appendChild(empty);
                return;
            }

            productsArray.forEach(product => {
                grid.appendChild(createProductCard(product));
            });
        }

        function handleImageError(img) {
            if (!img.dataset.retried) {
                img.dataset.retried = '1';
                img.src = '../../public/assets/images/Default.jpg';
            } else {
                // Use a simple solid color placeholder instead of complex SVG
                img.style.backgroundColor = '#e8ddd0';
                img.alt = 'Image not available';
            }
        }

        function createProductCard(product) {
            const card = document.createElement('div');
            card.className = 'product-card';
            const pointsText = product.points > 0 ? product.points + ' point' + (product.points !== 1 ? 's' : '') : 'No points';

            const img = document.createElement('img');
            img.src = product.image;
            img.alt = product.name;
            img.className = 'product-img';
            img.onerror = function() {
                handleImageError(this);
            };

            const info = document.createElement('div');
            info.className = 'product-info';
            info.innerHTML = `
                <div class="product-name">${product.name}</div>
                <div class="product-points">${pointsText}</div>
                <div class="product-price">₱${product.price.toFixed(2)}</div>
            `;

            card.appendChild(img);
            card.appendChild(info);
            return card;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const navLinks = document.getElementById('nav-links');
            const mobileSearchEl = document.getElementById('mobile-search');
            const headerSearchEl = document.querySelector('.header-center .search');
            // prefer mobile search if present (will be visible on tablet and below)
            const searchInput = mobileSearchEl || headerSearchEl || document.querySelector('.search');
            const categoryItems = Array.from(document.querySelectorAll('.categories-sidebar li'));
            const categoryToggle = document.getElementById('category-toggle');
            const categoriesSidebar = document.querySelector('.categories-sidebar');

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            // Track current category
            let currentCategory = allCategories && allCategories.length ? allCategories[0] : null;

            // Category click handlers
            categoryItems.forEach(li => {
                li.addEventListener('click', function() {
                    // clear search input when selecting a category
                    if (searchInput) searchInput.value = '';
                    const cat = this.textContent.trim();
                    currentCategory = cat;
                    renderProducts(cat);
                    // close sidebar on mobile after selection
                    if (categoriesSidebar && categoriesSidebar.classList.contains('mobile-open')) {
                        categoriesSidebar.classList.remove('mobile-open');
                    }
                });
            });

            // Search functionality (client-side)
            const doSearch = debounce(function() {
                const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                if (!q) {
                    // restore current category
                    if (currentCategory) {
                        renderProducts(currentCategory);
                    } else if (allCategories.length > 0) {
                        currentCategory = allCategories[0];
                        renderProducts(currentCategory);
                    }
                    return;
                }

                // Collect all products into a flat list
                const flat = [];
                for (const cat in productsData) {
                    if (!Array.isArray(productsData[cat])) continue;
                    productsData[cat].forEach(p => {
                        flat.push(Object.assign({
                            category: cat
                        }, p));
                    });
                }

                const results = flat.filter(p => p.name.toLowerCase().includes(q));
                renderProductsByList(results, `Search results for "${q}"`);
                // remove active class from sidebar while showing search
                document.querySelectorAll('.categories-sidebar li').forEach(l => l.classList.remove('active'));
            }, 250);

            if (searchInput) {
                searchInput.addEventListener('input', doSearch);
            }

            // Category toggle for mobile
            if (categoryToggle && categoriesSidebar) {
                categoryToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    categoriesSidebar.classList.toggle('mobile-open');
                });

                // close when clicking outside
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        if (categoriesSidebar.classList.contains('mobile-open')) {
                            if (!categoriesSidebar.contains(e.target) && !categoryToggle.contains(e.target)) {
                                categoriesSidebar.classList.remove('mobile-open');
                            }
                        }
                    }
                });
            }

            // Initial render: ensure Coffee-first ordering applied server-side
            if (allCategories.length > 0) {
                currentCategory = allCategories[0];
                renderProducts(currentCategory);
            }

            // QR modal handling for showing user's QR code to cashier
            const qrBtn = document.getElementById('qr-show-btn');
            const qrModal = document.getElementById('profile-qr-modal');
            const qrClose = document.getElementById('profile-qr-close');
            const qrContainer = document.getElementById('profile-qr-code');
            const qrInfo = document.getElementById('profile-qr-info');

            if (qrBtn && qrModal && qrClose && qrContainer) {
                qrBtn.addEventListener('click', function() {
                    // Clear any previous QR content
                    qrContainer.innerHTML = '';
                    if (qrInfo) qrInfo.innerHTML = ''; // remove displaying of available discounts

                    // Build payload: customer_id|first_name|last_name
                    const customerId = '<?php echo $customerId ? (int)$customerId : 0; ?>';
                    const customerName = '<?php echo addslashes($customerName); ?>';
                    const payload = customerId + '|' + customerName;

                    // Create a temporary hidden container so qrcode lib produces an <img>
                    const tmpDiv = document.createElement('div');
                    tmpDiv.style.position = 'absolute';
                    tmpDiv.style.left = '-9999px';
                    document.body.appendChild(tmpDiv);

                    try {
                        new QRCode(tmpDiv, {
                            text: payload,
                            width: 220,
                            height: 220
                        });
                    } catch (e) {
                        // If QR generation fails, fall back to plain text display
                        qrContainer.textContent = payload;
                        document.body.removeChild(tmpDiv);
                        qrModal.style.display = 'flex';
                        return;
                    }

                    // Allow a short timeout for the library to insert the image
                    setTimeout(function() {
                        // Attempt to find generated image
                        const tmpImg = tmpDiv.querySelector('img');
                        // Create canvas for final QR; size adapts to container width
                        const containerRect = qrContainer.getBoundingClientRect();
                        // subtract some padding so canvas fits comfortably
                        let computedSize = Math.floor(Math.min(320, containerRect.width - 20));
                        if (computedSize < 140) computedSize = 140;
                        const canvas = document.createElement('canvas');
                        const size = computedSize;
                        canvas.width = size;
                        canvas.height = size;
                        const ctx = canvas.getContext('2d');

                        if (tmpImg && tmpImg.src) {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                // Draw QR onto canvas sized to modal container
                                try {
                                    ctx.drawImage(img, 0, 0, size, size);
                                } catch (e) {
                                    ctx.fillStyle = '#fff';
                                    ctx.fillRect(0, 0, size, size);
                                }

                                // Draw a white rounded background for the logo (smaller so it doesn't obscure finder patterns)
                                const logoSize = Math.floor(size * 0.18); // ~18% of QR size
                                const logoX = Math.floor((size - logoSize) / 2);
                                const logoY = Math.floor((size - logoSize) / 2);

                                // White rounded box
                                const radius = 8;
                                ctx.fillStyle = '#ffffff';
                                ctx.beginPath();
                                ctx.moveTo(logoX + radius, logoY);
                                ctx.arcTo(logoX + logoSize, logoY, logoX + logoSize, logoY + logoSize, radius);
                                ctx.arcTo(logoX + logoSize, logoY + logoSize, logoX, logoY + logoSize, radius);
                                ctx.arcTo(logoX, logoY + logoSize, logoX, logoY, radius);
                                ctx.arcTo(logoX, logoY, logoX + logoSize, logoY, radius);
                                ctx.closePath();
                                ctx.fill();

                                // Draw logo image centered
                                const logo = new Image();
                                logo.crossOrigin = 'anonymous';
                                logo.src = '../../public/assets/css/images/logo images/logoName.png';
                                logo.onload = function() {
                                    // Fit logo into the white box with small padding
                                    const padding = Math.floor(logoSize * 0.12);
                                    const lw = logoSize - padding * 2;
                                    const lh = logoSize - padding * 2;
                                    ctx.drawImage(logo, logoX + padding, logoY + padding, lw, lh);

                                    // Style canvas for display
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';

                                    // Place canvas and download button into a centered wrapper to avoid overlap
                                    const wrapper = document.createElement('div');
                                    wrapper.style.display = 'flex';
                                    wrapper.style.flexDirection = 'column';
                                    wrapper.style.alignItems = 'center';
                                    wrapper.style.gap = '8px';
                                    wrapper.appendChild(canvas);

                                    const dl = document.createElement('a');
                                    dl.textContent = 'Download QR';
                                    dl.href = canvas.toDataURL('image/png');
                                    dl.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl.style.display = 'inline-block';
                                    dl.style.marginTop = '4px';
                                    dl.style.padding = '8px 12px';
                                    dl.style.background = '#6b4423';
                                    dl.style.color = '#fff';
                                    dl.style.borderRadius = '6px';
                                    dl.style.textDecoration = 'none';
                                    dl.style.fontFamily = "'Georgia', serif";
                                    dl.style.fontWeight = '600';
                                    dl.style.zIndex = '10';
                                    wrapper.appendChild(dl);
                                    qrContainer.appendChild(wrapper);

                                    // Cleanup
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };

                                logo.onerror = function() {
                                    // If logo fails, still show canvas and download inside wrapper
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                    const wrapper2 = document.createElement('div');
                                    wrapper2.style.display = 'flex';
                                    wrapper2.style.flexDirection = 'column';
                                    wrapper2.style.alignItems = 'center';
                                    wrapper2.style.gap = '8px';
                                    wrapper2.appendChild(canvas);
                                    const dl2 = document.createElement('a');
                                    dl2.textContent = 'Download QR';
                                    dl2.href = canvas.toDataURL('image/png');
                                    dl2.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl2.style.display = 'inline-block';
                                    dl2.style.marginTop = '4px';
                                    dl2.style.padding = '8px 12px';
                                    dl2.style.background = '#6b4423';
                                    dl2.style.color = '#fff';
                                    dl2.style.borderRadius = '6px';
                                    dl2.style.textDecoration = 'none';
                                    dl2.style.fontFamily = "'Georgia', serif";
                                    dl2.style.fontWeight = '600';
                                    dl2.style.zIndex = '10';
                                    wrapper2.appendChild(dl2);
                                    qrContainer.appendChild(wrapper2);
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };
                            };
                            img.onerror = function() {
                                // Fallback: if generated image cannot be loaded, fallback to simple text
                                qrContainer.textContent = payload;
                                if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                            };
                            img.src = tmpImg.src;
                        } else {
                            // If no image produced (some environments produce a table), fallback to direct QR rendering into container
                            qrContainer.innerHTML = '';
                            try {
                                // Render directly into container and wrap the image if possible
                                new QRCode(qrContainer, {
                                    text: payload,
                                    width: 220,
                                    height: 220
                                });
                                // If qrcodejs placed an img directly, wrap it
                                const directImg = qrContainer.querySelector('img');
                                if (directImg) {
                                    const canvas2 = document.createElement('canvas');
                                    const size2 = 220;
                                    canvas2.width = size2;
                                    canvas2.height = size2;
                                    const ctx2 = canvas2.getContext('2d');
                                    const tmpDirect = new Image();
                                    tmpDirect.crossOrigin = 'anonymous';
                                    tmpDirect.onload = function() {
                                        ctx2.drawImage(tmpDirect, 0, 0, size2, size2);
                                        canvas2.style.display = 'block';
                                        canvas2.style.background = '#ffffff';
                                        canvas2.style.borderRadius = '8px';
                                        canvas2.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                        const wrapper3 = document.createElement('div');
                                        wrapper3.style.display = 'flex';
                                        wrapper3.style.flexDirection = 'column';
                                        wrapper3.style.alignItems = 'center';
                                        wrapper3.style.gap = '8px';
                                        wrapper3.appendChild(canvas2);
                                        const dl3 = document.createElement('a');
                                        dl3.textContent = 'Download QR';
                                        dl3.href = canvas2.toDataURL('image/png');
                                        dl3.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                        dl3.style.display = 'inline-block';
                                        dl3.style.marginTop = '4px';
                                        dl3.style.padding = '8px 12px';
                                        dl3.style.background = '#6b4423';
                                        dl3.style.color = '#fff';
                                        dl3.style.borderRadius = '6px';
                                        dl3.style.textDecoration = 'none';
                                        dl3.style.fontFamily = "'Georgia', serif";
                                        dl3.style.fontWeight = '600';
                                        dl3.style.zIndex = '10';
                                        wrapper3.appendChild(dl3);
                                        qrContainer.innerHTML = '';
                                        qrContainer.appendChild(wrapper3);
                                    };
                                    tmpDirect.onerror = function() {
                                        // If we cannot load the direct image, leave the rendered table or image as-is
                                    };
                                    tmpDirect.src = directImg.src;
                                }
                            } catch (e) {
                                qrContainer.textContent = payload;
                            }
                            if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                        }

                        // Show modal after we've started generation
                        qrModal.style.display = 'flex';
                    }, 60);
                });

                qrClose.addEventListener('click', function() {
                    qrModal.style.display = 'none';
                });
                qrModal.addEventListener('click', function(e) {
                    if (e.target === qrModal) qrModal.style.display = 'none';
                });
            }
        });
    </script>
</head>

<body>
    <header class="serif header">
        <a href="home.php">
            <div class="header-left">

                <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo">

            </div>
        </a>
        <!-- <div class="header-center">
            <input type="text" class="search" placeholder="Search menu items...">
        </div> -->
        <div class="header-right">
            <nav id="nav-links">
                <a href="home.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="rewards.php">Rewards</a>

                <a href="faqs.php">FAQs</a>
            </nav>
            <div class="profile">
                <a href="profile.php">
                    <img src="<?php echo !empty($_SESSION['profile_image'])
                                    ? htmlspecialchars($_SESSION['profile_image'])
                                    : '../../public/icons/logo.png'; ?>"
                        alt="User">
                </a>
            </div>
            <nav id="nav-links">
                <a href="../../public/actions/auth/logout.php" class="logout">
                    <span class="material-symbols-outlined" alt="logout">logout</span>
                </a>
                <button id="qr-show-btn" class="profile-actions" title="Show QR" style="background:none;border:none;cursor:pointer;padding:0;margin-right:8px;">
                    <!-- simple QR SVG icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="15" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="3" y="15" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="11" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="14" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="11" y="14" width="2" height="2" fill="#ffffff" />
                    </svg>
                </button>
                <!-- Profile QR Modal -->
                <div class="qr-modal" id="profile-qr-modal" style="display:none;">
                    <div class="qr-modal-content" style="max-width:480px;padding:20px;">
                        <div style="display:flex;justify-content: flex-end;align-items:center;margin-bottom:12px;">

                            <button id="profile-qr-close" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
                        </div>
                        <div class="qr-modal-body">
                            <div id="profile-qr-code"></div>
                            <div style="flex:1;min-width:180px;">


                                <div id="profile-qr-logo" style="margin-top:12px;display:flex;align-items:center;justify-content: center;">
                                    <img id="profile-qr-logo-img" src="../../public/assets/css/images/logo images/logoName.png" alt="Cafe Logo" style="max-width:140px;max-height:100%;object-fit:contain;border-radius:6px;border:1px solid #eee;background:#fff;padding:6px;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            <button class="hamburger" id="hamburger-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <div class="banner-section">
        <img src="../../<?php echo htmlspecialchars($menu_cover_image); ?>" alt="Menu Banner" class="banner">
        <div class="serif banner-text"><?php echo htmlspecialchars($menu_cover_text); ?></div>
    </div>

    <div class="menu-container">
        <!-- Mobile controls: category toggle + search (visible on tablet and below) -->
        <div class="mobile-controls">
            <button id="category-toggle" class="category-toggle" aria-label="Toggle categories">☰ Categories</button>
            <!-- <input id="mobile-search" class="search mobile-search" type="text" placeholder="Search menu items..."> -->
        </div>
        <aside class="serif categories-sidebar">
            <h3>Categories</h3>
            <ul>
                <?php
                if (empty($menuCategories)) {
                    echo '<li>No categories</li>';
                } else {
                    foreach ($menuCategories as $index => $cat) {
                        $safe = htmlspecialchars($cat);
                        $active = $index === 0 ? 'active' : '';
                        echo "<li class=\"$active\">$safe</li>";
                    }
                }
                ?>
            </ul>
        </aside>

        <main class="products-section">
            <h2 class="serif category-title" id="category-title"><?php echo $initialCategory; ?></h2>
            <div class="product-grid" id="product-grid">
                <!-- Products rendered by JS -->
            </div>
        </main>
    </div>

    <footer class="footer">
        <div style="text-align:center; color:#fff; font-size:20px; margin-bottom:10px;">Contact us:</div>
        <div class="footer-contact-row">
            <div class="footer-contact-item">
                <img src="../../public/icons/call.png" alt="Phone">
                <span>0906 377 9569</span>
            </div>
            <div class="footer-contact-item">
                <img src="../../public/icons/placeholder.png" alt="Location">
                <span>Barangay Pinagtipunan, General Trias, Cavite</span>
            </div>
            <div class="footer-contact-item">
                <a href="https://web.facebook.com/profile.php?id=100095680143645" style="text-decoration: none;color:white;text-align:center;display: flex;align-items: center; gap: 12px;">
                    <img src="../../public/icons/communication.png" alt="Facebook">
                    <span>CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>

</html>