<?php

/**
 * Admin Members List Page
 * Protected page - only admins can access
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Include database configuration
require_once '../../public/actions/auth/db_config.php';

// Get admin name from session
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Fetch all customers from database
$customers = [];
$query = $conn->prepare("SELECT c.customer_id, c.first_name, c.last_name, c.email, c.phone, c.birthday, c.address, c.sex, c.occupation, c.tier_level, c.date_joined, c.image_path FROM customer c JOIN user u ON c.user_id = u.user_id ORDER BY c.customer_id DESC");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $query->close();
}

// Determine tier level based on points (you can adjust this logic)
function calculateTierLevel($customerId)
{
    // This is a placeholder - you can enhance this with actual points calculation
    $tiers = ['Bronze Level', 'Silver Level', 'Gold Level', 'Platinum Level'];
    return $tiers[$customerId % 4];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member List - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">    <script>
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
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });

            // Logout functionality
            const logoutBtn = document.querySelector('.admin-profile');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = '../../public/actions/auth/logout.php';
                    }
                });
            }

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    filterTable(e.target.value);
                });
            }

            // Sort dropdown functionality
            const sortDropdown = document.querySelector('.sort-dropdown');
            if (sortDropdown) {
                sortDropdown.addEventListener('change', function(e) {
                    sortTable(e.target.value);
                });
            }

            // View member details - using event delegation
            const tableBody = document.querySelector('.members-table tbody');
            if (tableBody) {
                tableBody.addEventListener('click', function(e) {
                    if (e.target.classList.contains('view-btn')) {
                        openMemberDetail(e.target);
                    }
                });
            }
        });

        function filterTable(searchTerm) {
            const table = document.querySelector('.members-table');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
        }

        function sortTable(sortBy) {
            const table = document.querySelector('.members-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                let aVal, bVal;

                if (sortBy === 'latest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent;
                    bVal = b.querySelector('td:nth-child(1)').textContent;
                    return parseInt(bVal) - parseInt(aVal);
                } else if (sortBy === 'oldest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent;
                    bVal = b.querySelector('td:nth-child(1)').textContent;
                    return parseInt(aVal) - parseInt(bVal);
                } else if (sortBy === 'name-asc') {
                    aVal = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    bVal = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    return aVal.localeCompare(bVal);
                } else if (sortBy === 'name-desc') {
                    aVal = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    bVal = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    return bVal.localeCompare(aVal);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        function openMemberDetail(button) {
            const row = button.closest('tr');
            const customerId = row.querySelector('td:nth-child(1)').textContent;
            const memberName = row.querySelector('td:nth-child(2)').textContent;
            const tierLevel = row.querySelector('td:nth-child(3)').textContent;
            const email = row.querySelector('td:nth-child(4)').textContent;
            const phone = row.getAttribute('data-phone');
            const birthday = row.getAttribute('data-birthday');
            const address = row.getAttribute('data-address');
            const sex = row.getAttribute('data-sex');
            const occupation = row.getAttribute('data-occupation');
            const dateJoined = row.getAttribute('data-date-joined');
            const imagePath = row.getAttribute('data-image');

            // Populate modal with member details
            document.getElementById('memberName').textContent = memberName;
            document.getElementById('memberId').textContent = 'ID: ' + customerId;
            document.getElementById('memberEmail').textContent = 'Email: ' + email;
            document.getElementById('memberTierLevel').textContent = 'Tier Level: ' + tierLevel;
            document.getElementById('memberPhone').textContent = 'Contact number: ' + phone;
            document.getElementById('memberBirthday').textContent = 'Birthday: ' + birthday;
            document.getElementById('memberAddress').textContent = 'Address: ' + address;
            document.getElementById('memberSex').textContent = 'Sex: ' + sex;
            document.getElementById('memberOccupation').textContent = 'Occupation: ' + occupation;
            document.getElementById('memberDateJoined').textContent = 'Joined Date: ' + dateJoined;

            // Set member image
            const memberImg = document.getElementById('memberImage');
            const memberPlaceholder = document.getElementById('memberImagePlaceholder');
            if (memberImg && imagePath) {
                memberImg.src = imagePath;
                memberImg.style.display = 'block';
                if (memberPlaceholder) memberPlaceholder.style.display = 'none';
            } else if (memberImg) {
                memberImg.style.display = 'none';
                if (memberPlaceholder) memberPlaceholder.style.display = 'flex';
            }

            const modal = document.getElementById('memberDetailModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        function closeMemberModal() {
            const modal = document.getElementById('memberDetailModal');
            modal.style.display = 'none';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('memberDetailModal');
            if (event.target === modal) {
                closeMemberModal();
            }
        });
    </script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="cafe-logo">
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon material-icons">dashboard</span>
                    <span class="nav-text">Dashboard</span>
                </a>
              
                <a href="menu.php" class="nav-link">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">Transactions</span>
                </a>
                <a href="rewards.php" class="nav-link">
                    <span class="nav-icon material-icons">confirmation_number</span>
                    <span class="nav-text">Rewards</span>
                </a>
                <a href="inventory.php" class="nav-link">
                    <span class="nav-icon material-icons">inventory_2</span>
                    <span class="nav-text">Inventory</span>
                </a>

                <a href="inventory_reports.php" class="nav-link">
                    <span class="nav-icon material-icons">inventory_2</span>
                    <span class="nav-text">Inventory Transactions</span>

                </a>
                <a href="members_list.php" class="nav-link active">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Members</span>
                </a>
                <a href="cashiers_list.php" class="nav-link">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Cashiers</span>
                </a>
                <a href="reports.php" class="nav-link">
                    <span class="nav-icon material-icons">assessment</span>
                    <span class="nav-text">Reports</span>
                </a>
                <a href="settings.php" class="nav-link">
                    <span class="nav-icon material-icons">settings</span>
                    <span class="nav-text">My Account</span>
                </a>
                  <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
                
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburger-menu-btn">☰</button>
                    <h1 class="serif page-title">Member List</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Members Content -->
            <div class="members-content">
                <!-- Controls Section -->
                <div class="members-controls">
                    <div class="sort-container">
                        <label for="sort-dropdown" class="sort-label">Latest</label>
                        <select id="sort-dropdown" class="sort-dropdown">
                            <option value="latest">Latest</option>
                            <option value="oldest">Oldest</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                        </select>
                    </div>

                </div>

                <!-- Members Table -->
                <div class="table-wrapper">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Tier Level</th>
                                <th>Email</th>
                                <th>Others</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr data-phone="<?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>" data-birthday="<?php echo htmlspecialchars($customer['birthday'] ?? 'N/A'); ?>" data-address="<?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?>" data-sex="<?php echo htmlspecialchars($customer['sex'] ?? 'N/A'); ?>" data-occupation="<?php echo htmlspecialchars($customer['occupation'] ?? 'N/A'); ?>" data-date-joined="<?php echo htmlspecialchars($customer['date_joined'] ?? 'N/A'); ?>" data-image="<?php echo htmlspecialchars($customer['image_path'] ?? ''); ?>">>
                                        <td><?php echo str_pad($customer['customer_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['tier_level'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><button class="action-btn " title="View"><img id="eyeIcon" class="view-btn" src="../../public/icons/eye-open.png" width="20" alt="Show/Hide"></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">No customers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Member Detail Modal -->
    <div id="memberDetailModal" class="modal">
        <div class="modal-content member-detail-modal" style="max-height:80vh; overflow-y:auto; -webkit-overflow-scrolling:touch;">
            <div class="modal-header" style="flex-direction: column; align-items: flex-end; border-bottom: none; padding-bottom: 0;">
                <button class="modal-close" onclick="closeMemberModal()">✕</button>
            </div>

            <div class="modal-body member-details">
                <!-- Member Avatar and Name -->
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="width: 120px; height: 120px; background-color: #999; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <img id="memberImage" src="" alt="Member" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: none;">
                        <div id="memberImagePlaceholder" style="width: 80px; height: 80px; background-color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #999;">👤</div>
                    </div>
                    <h2 id="memberName" style="margin: 0 0 15px 0; color: #333; font-size: 24px;">Jean Villanueva</h2>
                    <div style="border-bottom: 2px solid #333; margin: 0 auto; width: 80%; height: 0;"></div>
                </div>

                <!-- Member Information -->
                <div class="member-info-section">
                    <p id="memberId" style="margin: 12px 0; font-size: 15px; color: #333;">ID: 000004</p>
                    <p id="memberEmail" style="margin: 12px 0; font-size: 15px; color: #333;">Email: jeanvillanueva@gmail.com</p>
                    <p id="memberTierLevel" style="margin: 12px 0; font-size: 15px; color: #333;">Tier Level: Latte Level</p>
                    <p id="memberDateJoined" style="margin: 12px 0; font-size: 15px; color: #333;">Joined Date: 04/02/26</p>
                </div>

                <hr style="border: none; border-top: 2px solid #333; margin: 20px 0;">

                <!-- Additional Information -->
                <div class="member-info-section">
                    <p id="memberPhone" style="margin: 12px 0; font-size: 15px; color: #333;">Contact number: 09957289783</p>
                    <p id="memberBirthday" style="margin: 12px 0; font-size: 15px; color: #333;">Birthday: 08/10/2003</p>
                    <p id="memberAddress" style="margin: 12px 0; font-size: 15px; color: #333;">Address: 537 Masagana Compound, Sta. Clara, City of General Trias, Cavite</p>
                    <p id="memberSex" style="margin: 12px 0; font-size: 15px; color: #333;">Sex: Female</p>
                    <p id="memberOccupation" style="margin: 12px 0; font-size: 15px; color: #333;">Occupation: Student</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>