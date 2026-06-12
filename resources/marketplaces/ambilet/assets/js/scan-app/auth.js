/* =============================================================================
 * Scan App — auth wrapper
 * -----------------------------------------------------------------------------
 * Thin wrapper over the existing global `AmbiletAuth` (assets/js/auth.js) that
 * exposes the bits the scan app needs:
 *
 *   ScanAuth.isLoggedIn()         → boolean
 *   ScanAuth.getOrganizer()       → cached organizer object from localStorage
 *   ScanAuth.getTeamMember()      → team_member subdocument (or null if owner)
 *   ScanAuth.getUserRole()        → 'admin' | 'manager' | 'staff' | 'owner'
 *   ScanAuth.isAdmin()            → boolean — owner OR team_member admin
 *   ScanAuth.isStaff()            → boolean — team_member with role !== 'admin'
 *   ScanAuth.hasPermission(perm)  → boolean — owner=true, else checks permissions[]
 *   ScanAuth.userType()           → 'organizer' | 'venue_owner'
 *   ScanAuth.logout()             → redirects to /organizator/login
 *
 * DOES NOT mutate AmbiletAuth or localStorage shape; read-only over the
 * existing auth contract.
 * ============================================================================= */
(function () {
  'use strict';

  function rawOrganizerData() {
    if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.getOrganizerData) {
      try { return AmbiletAuth.getOrganizerData() || {}; } catch (e) { /* fall through */ }
    }
    try {
      return JSON.parse(localStorage.getItem('ambilet_organizer_data') || '{}');
    } catch (e) {
      return {};
    }
  }

  function rawUserType() {
    try {
      return localStorage.getItem('ambilet_user_type') || 'organizer';
    } catch (e) {
      return 'organizer';
    }
  }

  var ScanAuth = {
    isLoggedIn: function () {
      if (typeof AmbiletAuth === 'undefined' || !AmbiletAuth.isLoggedIn) return false;
      try { return !!AmbiletAuth.isLoggedIn(); } catch (e) { return false; }
    },

    getOrganizer: function () {
      return rawOrganizerData();
    },

    getTeamMember: function () {
      var d = rawOrganizerData();
      return d && d.team_member ? d.team_member : null;
    },

    getUserRole: function () {
      var tm = ScanAuth.getTeamMember();
      if (tm && tm.role) return tm.role;          // 'admin' | 'manager' | 'staff'
      return 'owner';                              // logged in as the organizer owner directly
    },

    isAdmin: function () {
      var role = ScanAuth.getUserRole();
      return role === 'owner' || role === 'admin';
    },

    isStaff: function () {
      var role = ScanAuth.getUserRole();
      return role === 'staff' || role === 'manager';
    },

    /**
     * @param {string} perm one of 'events','orders','reports','team','checkin'
     */
    hasPermission: function (perm) {
      var tm = ScanAuth.getTeamMember();
      if (!tm) return true;                        // owner — all permissions
      if (tm.role === 'admin') return true;        // admin team member — all permissions
      var list = tm.permissions || [];
      if (!Array.isArray(list)) return false;
      return list.indexOf(perm) !== -1;
    },

    userType: function () {
      var t = rawUserType();
      return t === 'venue_owner' ? 'venue_owner' : 'organizer';
    },

    isVenueOwner: function () {
      return ScanAuth.userType() === 'venue_owner';
    },

    logout: function () {
      if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.logoutOrganizer) {
        try { AmbiletAuth.logoutOrganizer(); return; } catch (e) { /* fall through */ }
      }
      // Last-resort fallback
      try { localStorage.removeItem('ambilet_organizer_token'); } catch (e) {}
      location.replace('/organizator/login');
    }
  };

  window.ScanAuth = ScanAuth;
})();
