<div id="driver-manager">
    <div class="section">
        <h2>Driver License Manager</h2>
        <p>Manage driver information and track license expiry dates</p>
        
        <button id="new-driver-btn" class="button primary">Add New Driver</button>
        
        <div id="driver-form" style="display: none;">
            <h3 id="form-title">Add New Driver</h3>
            <form id="driver-form-element">
                <input type="hidden" id="driver-id" value="">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="surname">Surname:</label>
                    <input type="text" id="surname" name="surname" required>
                </div>
                <div class="form-group">
                    <label for="license-number">License Number:</label>
                    <input type="text" id="license-number" name="licenseNumber" required>
                </div>
                <div class="form-group">
                    <label for="license-expiry">License Expiry Date (DD/MM/YYYY):</label>
                    <input type="text" id="license-expiry" name="licenseExpiry" 
                           placeholder="DD/MM/YYYY" pattern="\d{2}/\d{2}/\d{4}" 
                           maxlength="10" required>
                    <small>Format: DD/MM/YYYY (e.g., 25/12/2025)</small>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="button primary">Save</button>
                    <button type="button" id="cancel-btn" class="button">Cancel</button>
                </div>
            </form>
        </div>
        
        <div id="drivers-list">
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
        </div>
    </div>
</div>