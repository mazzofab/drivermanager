<div id="driver-manager">
    <div class="section">
        <h2>Driver License Manager</h2>
        <p>Manage driver information and track license expiry dates</p>
        
        <!-- Search and Controls Section -->
        <div class="controls-section">
            <div class="search-controls">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search by name, surname, or license number..." />
                    <button type="button" id="search-btn" class="search-button">🔍</button>
                    <button type="button" id="clear-search-btn" class="clear-button" style="display: none;">✕</button>
                </div>
                <button id="new-driver-btn" class="button primary">Add New Driver</button>
                <button id="test-notification-btn" class="button" style="background-color: #fd7e14; color: white; border-color: #fd7e14;" title="Send test email and push notifications (for testing purposes)">📧 Test Notifications</button>
            </div>
            
            <!-- Status Filter Buttons -->
            <div class="status-filters">
                <label style="font-weight: bold; margin-right: 10px;">Filter by Status:</label>
                <button type="button" id="filter-all" class="status-filter-btn active" data-status="all">
                    <span class="filter-icon">📋</span> All Drivers
                    <span class="filter-count" id="count-all">0</span>
                </button>
                <button type="button" id="filter-valid" class="status-filter-btn" data-status="valid">
                    <span class="filter-icon">🟢</span> Valid
                    <span class="filter-count" id="count-valid">0</span>
                </button>
                <button type="button" id="filter-expiring" class="status-filter-btn" data-status="expiring">
                    <span class="filter-icon">🟡</span> Expiring Soon
                    <span class="filter-count" id="count-expiring">0</span>
                </button>
                <button type="button" id="filter-expired" class="status-filter-btn" data-status="expired">
                    <span class="filter-icon">🔴</span> Expired
                    <span class="filter-count" id="count-expired">0</span>
                </button>
            </div>
            
            <!-- Results Info and Pagination Controls -->
            <div class="results-info">
                <span id="results-count">Loading...</span>
                <div class="pagination-controls">
                    <button id="first-page" class="page-btn" disabled>⏮</button>
                    <button id="prev-page" class="page-btn" disabled>◀</button>
                    <span id="page-info">Page 1 of 1</span>
                    <button id="next-page" class="page-btn" disabled>▶</button>
                    <button id="last-page" class="page-btn" disabled>⏭</button>
                    <select id="page-size" class="page-size-select">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div id="driver-form" style="display: none;">
            <h3 id="form-title">Add New Driver</h3>
            <form id="driver-form-element">
                <input type="hidden" id="driver-id" value="">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" placeholder="e.g., John" required>
                </div>
                <div class="form-group">
                    <label for="surname">Surname:</label>
                    <input type="text" id="surname" name="surname" placeholder="e.g., Rodrick" required>
                </div>
                <div class="form-group">
                    <label for="license-number">License Number:</label>
                    <input type="text" id="license-number" name="licenseNumber" placeholder="e.g., MI123456A" required>
                    <small>Letters and numbers only, automatically converted to uppercase</small>
                </div>
                <div class="form-group">
                    <label for="license-expiry">License Expiry Date:</label>
                    <div class="date-input-wrapper">
                        <input type="text" id="license-expiry" name="licenseExpiry" 
                               placeholder="DD/MM/YYYY" readonly required>
                        <button type="button" id="date-picker-btn" class="date-picker-button">📅</button>
                    </div>
                    <small>Click the calendar icon to select a date</small>
                    
                    <!-- Custom datepicker popup -->
                    <div id="custom-datepicker" class="datepicker-popup" style="display: none;">
                        <div class="datepicker-header">
                            <button type="button" id="prev-month" class="datepicker-nav">‹</button>
                            <span id="current-month-year"></span>
                            <button type="button" id="next-month" class="datepicker-nav">›</button>
                        </div>
                        <div class="datepicker-weekdays">
                            <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
                        </div>
                        <div id="datepicker-days" class="datepicker-days"></div>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="button primary">Save</button>
                    <button type="button" id="cancel-btn" class="button">Cancel</button>
                </div>
            </form>
        </div>
        
        <div id="drivers-list">
            <div id="loading-indicator" style="display: none;">
                <p>Loading drivers...</p>
            </div>
            <table id="drivers-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>License Number</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div id="no-results" style="display: none;">
                <p>No drivers found matching your search criteria.</p>
            </div>
        </div>
    </div>
</div>
