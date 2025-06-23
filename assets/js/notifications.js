// Notification Functions
function loadNotifications() {
    $.get('../admin/get_notifications.php', function(response) {
        const data = JSON.parse(response);
        const notificationsList = $('.notifications-list');
        notificationsList.empty();

        if (data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(notification => {
                // Get the appropriate icon based on notification type
                let icon = '';
                switch (notification.type) {
                    case 'booking':
                        icon = 'bi-calendar-check';
                        break;
                    case 'cancellation':
                        icon = 'bi-calendar-x';
                        break;
                    case 'maintenance':
                        icon = 'bi-tools';
                        break;
                    case 'warning':
                        icon = 'bi-exclamation-triangle';
                        break;
                    case 'danger':
                        icon = 'bi-exclamation-circle';
                        break;
                    case 'success':
                        icon = 'bi-check-circle';
                        break;
                    case 'info':
                    default:
                        icon = 'bi-info-circle';
                        break;
                }

                const notificationHtml = `
                    <div class="notification-item ${notification.type} ${!notification.is_read ? 'unread' : ''}" 
                         data-id="${notification.id}" 
                         ${notification.link ? `data-link="${notification.link}"` : ''}>
                        <div class="notification-title">
                            <i class="bi ${icon}"></i>
                            ${notification.title}
                        </div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            <i class="bi bi-clock text-muted" style="font-size: 0.7rem;"></i>
                            ${notification.formatted_date}
                        </div>
                    </div>
                `;
                notificationsList.append(notificationHtml);
            });
        } else {
            notificationsList.html(`
                <div class="notification-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `);
        }
    });
}

function updateNotificationBadge() {
    $.get('../admin/get_notifications.php?count=1', function(response) {
        const data = JSON.parse(response);
        const badge = $('.notification-badge');
        if (data.count > 0) {
            badge.text(data.count).show();
        } else {
            badge.hide();
        }
    });
}

// Initialize Notifications
$(document).ready(function() {
    // Add notification HTML to header if not exists
    if ($('#notificationDropdown').length === 0) {
        const notificationHtml = `
            <div class="header-icon dropdown">
                <button class="btn p-0 border-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notificationDropdown">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">
                        0
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <div class="notification-header border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Notifications</h6>
                            <button class="btn btn-link text-decoration-none p-0" id="markAllRead">Mark all as read</button>
                        </div>
                    </div>
                    <div class="notification-body">
                        <div class="notifications-list p-3">
                            <!-- Notifications will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        `;
        // Find the moon icon and insert notification bell before it
        $('.header-icon .bi-moon').parent().before(notificationHtml);
    }

    // Load notifications on page load
    loadNotifications();
    updateNotificationBadge();

    // Refresh notifications every 30 seconds
    setInterval(function() {
        loadNotifications();
        updateNotificationBadge();
    }, 30000);

    // Mark notification as read when clicked
    $(document).on('click', '.notification-item', function(e) {
        e.preventDefault();
        const notificationId = $(this).data('id');
        const $this = $(this);
        const link = $(this).data('link');

        $.post('../admin/mark_notification_read.php', { notification_id: notificationId }, function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                $this.removeClass('unread');
                updateNotificationBadge();
                if (link) {
                    window.location.href = link;
                }
            }
        });
    });

    // Mark all notifications as read
    $(document).on('click', '#markAllRead', function(e) {
        e.preventDefault();
        e.stopPropagation();

        $.post('../admin/mark_notification_read.php', function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                $('.notification-item').removeClass('unread');
                updateNotificationBadge();
            }
        });
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.notification-dropdown').length && 
            !$(e.target).closest('#notificationDropdown').length) {
            $('.notification-dropdown').removeClass('show');
        }
    });

    // Prevent dropdown from closing when clicking inside
    $(document).on('click', '.notification-dropdown', function(e) {
        e.stopPropagation();
    });
}); 