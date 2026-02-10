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
                <a href="../../public/actions/auth/logout.php">Logout</a>
                <a href="">QR Code</a>
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