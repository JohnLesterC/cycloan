/**
 * CYCLOAN Notification System JavaScript
 * Real-time notification management and UI interactions
 *
 * @author CYCLOAN Development Team
 * @version 1.0
 * @created 2025-11-13
 */

class NotificationSystem {
  constructor(options = {}) {
    this.options = {
      pollInterval: 30000, // 30 seconds
      maxRetries: 3,
      retryDelay: 1000,
      soundEnabled: true,
      showToasts: true,
      maxToasts: 3,
      ...options,
    };

    this.currentUser = options.currentUser || null;
    this.isPolling = false;
    this.pollTimer = null;
    this.retryCount = 0;
    this.lastNotificationCheck = null;
    this.audioContext = null;
    this.notificationSound = null;

    this.init();
  }

  init() {
    this.createNotificationElements();
    this.bindEvents();
    this.startPolling();
    this.loadUnreadCount();
    this.initializeAudio();
  }

  createNotificationElements() {
    // Create notification container if it doesn't exist
    if (!document.querySelector(".notification-container")) {
      const container = document.createElement("div");
      container.className = "notification-container";
      container.innerHTML = `
                <button class="notification-bell" id="notificationBell" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <div class="notification-actions">
                            <button onclick="notificationSystem.markAllAsRead()" title="Mark all as read">
                                <i class="fas fa-check-double"></i>
                            </button>
                            <button onclick="notificationSystem.refreshNotifications()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <!-- Notifications will be loaded here -->
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php">View All Notifications</a>
                    </div>
                </div>
            `;

      // Insert before profile container
      const profileContainer = document.querySelector(".profile-container");
      if (profileContainer) {
        profileContainer.parentNode.insertBefore(container, profileContainer);
      }
    }

    // Create toast container
    if (!document.querySelector(".toast-container")) {
      const toastContainer = document.createElement("div");
      toastContainer.className = "toast-container";
      toastContainer.id = "toastContainer";
      document.body.appendChild(toastContainer);
    }
  }

  bindEvents() {
    const bell = document.getElementById("notificationBell");
    const dropdown = document.getElementById("notificationDropdown");

    if (bell) {
      bell.addEventListener("click", (e) => {
        e.stopPropagation();
        this.toggleDropdown();
      });
    }

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".notification-container")) {
        this.closeDropdown();
      }
    });

    // Keyboard navigation
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeDropdown();
      }
    });
  }

  startPolling() {
    if (this.isPolling) return;

    this.isPolling = true;
    this.pollTimer = setInterval(() => {
      this.checkForNewNotifications();
    }, this.options.pollInterval);

    // Initial check
    this.checkForNewNotifications();
  }

  stopPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
    this.isPolling = false;
  }

  async checkForNewNotifications() {
    try {
      const response = await fetch("api/notifications.php?action=check_new", {
        method: "GET",
        credentials: "include",
        headers: {
          "Cache-Control": "no-cache",
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        this.updateNotificationBadge(data.unread_count);

        // Show new notifications as toasts
        if (data.new_notifications && data.new_notifications.length > 0) {
          this.handleNewNotifications(data.new_notifications);
        }

        this.retryCount = 0; // Reset retry count on success
      }
    } catch (error) {
      console.error("Error checking notifications:", error);
      this.handlePollingError();
    }
  }

  handlePollingError() {
    this.retryCount++;

    if (this.retryCount <= this.options.maxRetries) {
      // Exponential backoff
      const delay = this.options.retryDelay * Math.pow(2, this.retryCount - 1);
      setTimeout(() => {
        this.checkForNewNotifications();
      }, delay);
    } else {
      // Stop polling after max retries, show error
      this.stopPolling();
      this.showToast(
        "Connection Error",
        "Unable to check for new notifications. Please refresh the page.",
        "error"
      );
    }
  }

  handleNewNotifications(newNotifications) {
    newNotifications.forEach((notification) => {
      if (this.options.showToasts) {
        this.showNotificationToast(notification);
      }

      if (this.options.soundEnabled) {
        this.playNotificationSound(notification.priority);
      }
    });

    // Update bell animation
    const bell = document.getElementById("notificationBell");
    if (bell && newNotifications.length > 0) {
      bell.classList.add("has-notifications");
      setTimeout(() => {
        bell.classList.remove("has-notifications");
      }, 3000);
    }
  }

  toggleDropdown() {
    const dropdown = document.getElementById("notificationDropdown");
    if (dropdown.classList.contains("show")) {
      this.closeDropdown();
    } else {
      this.openDropdown();
    }
  }

  async openDropdown() {
    const dropdown = document.getElementById("notificationDropdown");
    dropdown.classList.add("show");

    // Load recent notifications
    await this.loadRecentNotifications();
  }

  closeDropdown() {
    const dropdown = document.getElementById("notificationDropdown");
    dropdown.classList.remove("show");
  }

  async loadRecentNotifications() {
    const list = document.getElementById("notificationList");
    list.innerHTML =
      '<div class="notification-loading"><div class="spinner"></div> Loading...</div>';

    try {
      const response = await fetch(
        "api/notifications.php?action=get_recent&limit=10"
      );
      const data = await response.json();

      if (data.success) {
        this.renderNotificationList(data.notifications);
      } else {
        throw new Error(data.message || "Failed to load notifications");
      }
    } catch (error) {
      console.error("Error loading notifications:", error);
      list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Error Loading Notifications</h4>
                    <p>Unable to load notifications. Please try again.</p>
                </div>
            `;
    }
  }

  renderNotificationList(notifications) {
    const list = document.getElementById("notificationList");

    if (!notifications || notifications.length === 0) {
      list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <h4>No Notifications</h4>
                    <p>You're all caught up! No new notifications.</p>
                </div>
            `;
      return;
    }

    list.innerHTML = notifications
      .map((notification) => this.renderNotificationItem(notification))
      .join("");
  }

  renderNotificationItem(notification) {
    const isUnread = !notification.is_read;
    const priorityClass = notification.priority || "normal";
    const timeAgo = this.formatTimeAgo(notification.created_at);

    return `
            <div class="notification-item ${
              isUnread ? "unread" : ""
            } ${priorityClass}" 
                 onclick="notificationSystem.handleNotificationClick('${
                   notification.notification_id
                 }', '${notification.action_url || "#"}')"
                 role="button" tabindex="0">
                <div class="notification-icon ${
                  notification.color_class || "primary"
                }">
                    <i class="${notification.icon_class || "fa-bell"}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(
                      notification.title
                    )}</div>
                    <div class="notification-message">${this.escapeHtml(
                      notification.short_message || notification.message
                    )}</div>
                    <div class="notification-meta">
                        <span class="notification-time">${timeAgo}</span>
                        <span class="notification-type">${
                          notification.type_display_name
                        }</span>
                    </div>
                </div>
                <div class="notification-actions-item">
                    <button onclick="event.stopPropagation(); notificationSystem.markAsRead('${
                      notification.notification_id
                    }')" 
                            title="Mark as read" style="${
                              isUnread ? "" : "display: none;"
                            }">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
        `;
  }

  async loadUnreadCount() {
    try {
      const response = await fetch(
        "api/notifications.php?action=get_unread_count"
      );
      const data = await response.json();

      if (data.success) {
        this.updateNotificationBadge(data.total);
      }
    } catch (error) {
      console.error("Error loading unread count:", error);
    }
  }

  updateNotificationBadge(count) {
    const badge = document.getElementById("notificationBadge");
    const bell = document.getElementById("notificationBell");

    if (badge && bell) {
      if (count > 0) {
        badge.textContent = count > 99 ? "99+" : count;
        badge.style.display = "flex";
        bell.classList.add("has-notifications");

        // Update badge class based on count
        if (count > 10) {
          badge.classList.add("high-priority");
        } else {
          badge.classList.remove("high-priority");
        }
      } else {
        badge.style.display = "none";
        bell.classList.remove("has-notifications");
        badge.classList.remove("high-priority");
      }
    }
  }

  async markAsRead(notificationId) {
    try {
      const response = await fetch("api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "mark_read",
          notification_ids: [notificationId],
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update UI
        const item = document.querySelector(`[onclick*="${notificationId}"]`);
        if (item) {
          item.classList.remove("unread");
          const markButton = item.querySelector(
            ".notification-actions-item button"
          );
          if (markButton) {
            markButton.style.display = "none";
          }
        }

        // Update badge
        await this.loadUnreadCount();

        this.showToast(
          "Success",
          "Notification marked as read",
          "success",
          2000
        );
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      console.error("Error marking notification as read:", error);
      this.showToast("Error", "Failed to mark notification as read", "error");
    }
  }

  async markAllAsRead() {
    try {
      const response = await fetch("api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "mark_all_read",
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update UI
        document
          .querySelectorAll(".notification-item.unread")
          .forEach((item) => {
            item.classList.remove("unread");
            const markButton = item.querySelector(
              ".notification-actions-item button"
            );
            if (markButton) {
              markButton.style.display = "none";
            }
          });

        // Update badge
        this.updateNotificationBadge(0);

        this.showToast(
          "Success",
          "All notifications marked as read",
          "success"
        );
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      console.error("Error marking all notifications as read:", error);
      this.showToast(
        "Error",
        "Failed to mark all notifications as read",
        "error"
      );
    }
  }

  async refreshNotifications() {
    await this.loadRecentNotifications();
    await this.loadUnreadCount();
    this.showToast("Success", "Notifications refreshed", "success", 2000);
  }

  handleNotificationClick(notificationId, actionUrl) {
    // Mark as read
    this.markAsRead(notificationId);

    // Navigate to action URL if provided
    if (actionUrl && actionUrl !== "#") {
      // Close dropdown first
      this.closeDropdown();

      // Navigate after a short delay
      setTimeout(() => {
        window.location.href = actionUrl;
      }, 200);
    }
  }

  showNotificationToast(notification) {
    const type = this.getToastType(
      notification.type_name,
      notification.priority
    );
    const title = notification.title;
    const message = notification.short_message || notification.message;

    this.showToast(title, message, type, 5000);
  }

  getToastType(typeName, priority) {
    if (priority === "urgent" || priority === "high") {
      return "warning";
    }

    switch (typeName) {
      case "payment":
        return "success";
      case "status":
        return "info";
      case "security":
        return "warning";
      case "approval":
        return "success";
      case "document":
        return "info";
      default:
        return "info";
    }
  }

  showToast(title, message, type = "info", duration = 4000) {
    const container = document.getElementById("toastContainer");
    if (!container) return;

    // Limit number of toasts
    const existingToasts = container.querySelectorAll(".toast");
    if (existingToasts.length >= this.options.maxToasts) {
      existingToasts[0].remove();
    }

    const toastId = "toast_" + Date.now();
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.id = toastId;
    toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon ${type}">
                    <i class="${this.getToastIcon(type)}"></i>
                </div>
                <div class="toast-text">
                    <div class="toast-title">${this.escapeHtml(title)}</div>
                    <div class="toast-message">${this.escapeHtml(message)}</div>
                </div>
                <button class="toast-close" onclick="notificationSystem.closeToast('${toastId}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

    container.appendChild(toast);

    // Show toast
    setTimeout(() => {
      toast.classList.add("show");
    }, 100);

    // Auto remove
    if (duration > 0) {
      setTimeout(() => {
        this.closeToast(toastId);
      }, duration);
    }
  }

  getToastIcon(type) {
    switch (type) {
      case "success":
        return "fa-check";
      case "error":
        return "fa-times";
      case "warning":
        return "fa-exclamation-triangle";
      case "info":
        return "fa-info";
      default:
        return "fa-bell";
    }
  }

  closeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
      toast.classList.remove("show");
      setTimeout(() => {
        toast.remove();
      }, 300);
    }
  }

  initializeAudio() {
    if ("webkitAudioContext" in window || "AudioContext" in window) {
      try {
        this.audioContext = new (window.AudioContext ||
          window.webkitAudioContext)();
      } catch (e) {
        console.warn("Audio context not supported");
      }
    }
  }

  playNotificationSound(priority = "normal") {
    if (!this.options.soundEnabled || !this.audioContext) return;

    try {
      // Create a simple beep sound
      const oscillator = this.audioContext.createOscillator();
      const gainNode = this.audioContext.createGain();

      oscillator.connect(gainNode);
      gainNode.connect(this.audioContext.destination);

      // Different frequencies for different priorities
      const frequencies = {
        urgent: 800,
        high: 600,
        normal: 400,
        low: 300,
      };

      oscillator.frequency.setValueAtTime(
        frequencies[priority] || 400,
        this.audioContext.currentTime
      );
      oscillator.type = "sine";

      gainNode.gain.setValueAtTime(0.1, this.audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(
        0.01,
        this.audioContext.currentTime + 0.5
      );

      oscillator.start(this.audioContext.currentTime);
      oscillator.stop(this.audioContext.currentTime + 0.5);
    } catch (error) {
      console.warn("Error playing notification sound:", error);
    }
  }

  formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);

    if (diffInSeconds < 60) {
      return "Just now";
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60);
      return `${minutes} min${minutes > 1 ? "s" : ""} ago`;
    } else if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600);
      return `${hours} hr${hours > 1 ? "s" : ""} ago`;
    } else if (diffInSeconds < 604800) {
      const days = Math.floor(diffInSeconds / 86400);
      return `${days} day${days > 1 ? "s" : ""} ago`;
    } else {
      return time.toLocaleDateString();
    }
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  // Public API methods
  enable() {
    this.startPolling();
  }

  disable() {
    this.stopPolling();
  }

  updateSettings(newOptions) {
    this.options = { ...this.options, ...newOptions };

    if (newOptions.pollInterval) {
      this.stopPolling();
      this.startPolling();
    }
  }
}

// Global notification system instance
let notificationSystem;

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  // Get current user info from session or data attributes
  const currentUser = {
    id: document.body.dataset.userId,
    role: document.body.dataset.userRole,
  };

  // Initialize notification system
  notificationSystem = new NotificationSystem({
    currentUser: currentUser,
    pollInterval: 30000, // Check every 30 seconds
    soundEnabled: true,
    showToasts: true,
  });
});

// Handle page visibility changes to optimize polling
document.addEventListener("visibilitychange", function () {
  if (notificationSystem) {
    if (document.hidden) {
      // Page is hidden, reduce polling frequency
      notificationSystem.updateSettings({
        pollInterval: 60000, // 1 minute when hidden
      });
    } else {
      // Page is visible, normal polling frequency
      notificationSystem.updateSettings({
        pollInterval: 30000, // 30 seconds when visible
      });

      // Check for new notifications immediately
      notificationSystem.checkForNewNotifications();
    }
  }
});

// Handle online/offline events
window.addEventListener("online", function () {
  if (notificationSystem) {
    notificationSystem.enable();
    notificationSystem.showToast(
      "Connection Restored",
      "You are back online. Checking for new notifications...",
      "success"
    );
  }
});

window.addEventListener("offline", function () {
  if (notificationSystem) {
    notificationSystem.disable();
    notificationSystem.showToast(
      "Connection Lost",
      "You are offline. Notifications will resume when connection is restored.",
      "warning"
    );
  }
});

// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = NotificationSystem;
}
