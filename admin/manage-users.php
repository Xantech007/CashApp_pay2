<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Users</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Manage Users</li>
            </ol>
        </nav>
    </div>

    <!-- ==================== SEARCH BAR ==================== -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1">
                    <input type="text" name="q" class="form-control" placeholder="Search by name or email..."
                           value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                <?php if (!empty($_GET['q'])): ?>
                <div class="col-auto">
                    <a href="?" class="btn btn-outline-secondary">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <!-- ==================================================== -->

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-borderless" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Referred By</th>
                            <th>Profile</th>
                            <th>Verification Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ==================== PAGINATION + SEARCH SETUP ====================
                        $limit = 25;
                        $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $offset = ($page - 1) * $limit;
                        $search = trim($_GET['q'] ?? '');

                        // Build WHERE clause for server-side search
                        $where = '';
                        $params = [];
                        $types  = '';
                        if ($search !== '') {
                            $where = "WHERE name LIKE ? OR email LIKE ?";
                            $like  = "%{$search}%";
                            $params = [$like, $like];
                            $types  = 'ss';
                        }

                        // Count total (filtered) rows
                        $count_sql = "SELECT COUNT(*) AS total FROM users $where";
                        $stmt = $con->prepare($count_sql);
                        if ($params) $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $total_users = $stmt->get_result()->fetch_assoc()['total'];
                        $total_pages = max(1, ceil($total_users / $limit));

                        // Fetch current page rows
                        $sql = "SELECT id, name, email, refered_by, image, verify 
                                FROM users $where
                                ORDER BY id DESC 
                                LIMIT ? OFFSET ?";
                        $stmt = $con->prepare($sql);
                        if ($params) {
                            $stmt->bind_param($types . 'ii', ...$params, $limit, $offset);
                        } else {
                            $stmt->bind_param('ii', $limit, $offset);
                        }
                        $stmt->execute();
                        $query_run = $stmt->get_result();

                        if ($query_run->num_rows > 0) {
                            foreach ($query_run as $data) {
                                $verify_status = match ((int)$data['verify']) {
                                    0 => 'Not Verified',
                                    1 => 'Under Review',
                                    2 => 'Verified',
                                    3 => 'Partial',
                                    default => 'Not Verified'
                                };
                                $badge = match ((int)$data['verify']) {
                                    0, null => 'bg-danger',
                                    1 => 'bg-warning',
                                    2 => 'bg-success',
                                    3 => 'bg-purple',
                                    default => 'bg-danger'
                                };
                        ?>
                                <tr>
                                    <td><?= $data['id'] ?></td>
                                    <td class="searchable"><?= htmlspecialchars($data['name']) ?></td>
                                    <td class="searchable"><?= htmlspecialchars($data['email']) ?></td>
                                    <td><?= htmlspecialchars($data['refered_by'] ?? '-') ?></td>
                                    <td>
                                        <img src="../Uploads/profile-picture/<?= htmlspecialchars($data['image']) ?>"
                                             style="width:50px;height:50px;border-radius:50%;object-fit:cover;"
                                             loading="lazy" alt="Profile">
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge ?>"><?= $verify_status ?></span>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-1 verify-btn"
                                                data-id="<?= $data['id'] ?>"
                                                data-name="<?= htmlspecialchars($data['name']) ?>"
                                                data-status="<?= (int)$data['verify'] ?>">
                                            Change
                                        </button>
                                    </td>
                                    <td>
                                        <a href="edit-user?id=<?= $data['id'] ?>" class="btn btn-light">Edit</a>
                                    </td>
                                    <td>
                                        <form action="codes/users.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="profile_pic" value="<?= htmlspecialchars($data['image']) ?>">
                                            <button class="btn btn-outline-danger" name="delete_user" value="<?= $data['id'] ?>">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">No users found.</td></tr>';
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>

                <!-- ==================== PAGINATION ==================== -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl($page - 1, $search) ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildUrl($i, $search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl($page + 1, $search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL (unchanged) ==================== -->
    <div class="modal fade" id="verifyModal" tabindex="-1">...</div>

    <!-- ==================== STYLES ==================== -->
    <style>
        .bg-purple { background-color:#6f42c1 !important; color:#fff !important; }
        /* Highlight matched text (optional) */
        .highlight { background:#fff3cd; }
    </style>

    <!-- ==================== SCRIPTS ==================== -->
    <script>
    // ---------- Preserve search term in pagination ----------
    function buildUrl(page, term) {
        const params = new URLSearchParams();
        if (term) params.set('q', term);
        params.set('page', page);
        return '?' + params.toString();
    }

    // ---------- Client-side live filter (fallback) ----------
    const searchInput = document.querySelector('input[name="q"]');
    const tableRows   = document.querySelectorAll('#usersTable tbody tr');
    const searchable  = document.querySelectorAll('.searchable');

    function filterTable() {
        const term = searchInput.value.toLowerCase();
        tableRows.forEach(row => {
            const cells = row.querySelectorAll('.searchable');
            let found = false;
            cells.forEach(cell => {
                const txt = cell.textContent.toLowerCase();
                cell.classList.toggle('highlight', txt.includes(term) && term);
                if (txt.includes(term)) found = true;
            });
            row.style.display = found || !term ? '' : 'none';
        });
    }

    // Debounce live search (optional, removes flicker)
    let timeout;
    searchInput?.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(filterTable, 250);
    });

    // ---------- Modal logic (unchanged) ----------
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.verify-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const status = this.dataset.status;

                document.getElementById('modalUserId').value = id;
                document.getElementById('modalUserName').textContent = name;
                document.querySelector('#verifyModal select').value = status;

                const modal = new bootstrap.Modal(document.getElementById('verifyModal'));
                modal.show();
            });
        });
    });
    </script>
</main>

<?php
// Helper used in pagination links
function buildUrl($page, $search) {
    $params = ['page' => $page];
    if ($search !== '') $params['q'] = $search;
    return '?' . http_build_query($params);
}
include('inc/footer.php');
?>
</html>
