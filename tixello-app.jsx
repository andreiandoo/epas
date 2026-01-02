import React, { useState, useEffect } from 'react';

// Tixello Event Staff App - Complete Mobile Interface

const cssStyles = `
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:wght@400;500&display=swap');

  * { margin: 0; padding: 0; box-sizing: border-box; }

  .app-container {
    font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #0A0A0F;
    min-height: 100vh;
    color: #fff;
    max-width: 430px;
    margin: 0 auto;
    position: relative;
    overflow-x: hidden;
  }

  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(180deg, rgba(10, 10, 15, 1) 0%, rgba(10, 10, 15, 0.95) 100%);
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.05);
  }

  .header-left, .header-right { display: flex; align-items: center; gap: 12px; }
  .logo { display: flex; align-items: center; gap: 10px; }
  .logo-icon { width: 32px; height: 32px; }
  .logo-text { font-size: 20px; font-weight: 700; background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.8) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -0.5px; }

  .connection-status { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .connection-status.online { background: rgba(16, 185, 129, 0.15); color: #10B981; }
  .connection-status.offline { background: rgba(239, 68, 68, 0.15); color: #EF4444; }
  .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: pulse 2s infinite; }

  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

  .notification-btn { position: relative; width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
  .notification-btn:hover { background: rgba(255,255,255,0.1); }
  .notification-btn svg { width: 20px; height: 20px; }
  .notification-badge { position: absolute; top: -4px; right: -4px; width: 18px; height: 18px; border-radius: 50%; background: #EF4444; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

  .event-selector { display: flex; align-items: center; gap: 12px; padding: 14px 20px; background: rgba(139, 92, 246, 0.08); border-bottom: 1px solid rgba(139, 92, 246, 0.2); cursor: pointer; transition: all 0.2s; }
  .event-selector:hover { background: rgba(139, 92, 246, 0.12); }
  .event-info { flex: 1; }
  .event-name { display: block; font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 2px; }
  .event-meta { font-size: 12px; color: rgba(255,255,255,0.5); }
  .event-status { display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
  .event-status.live { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .event-status.upcoming { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); }
  .event-status.ended { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); }
  .event-status .pulse { width: 6px; height: 6px; border-radius: 50%; background: #10B981; animation: pulse 1.5s infinite; }
  .chevron { width: 20px; height: 20px; color: rgba(255,255,255,0.4); }

  .main-content { padding-bottom: 90px; }

  .dashboard { padding: 20px; }
  
  /* Reports Only Banner */
  .reports-only-banner { display: flex; align-items: center; gap: 14px; padding: 16px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 14px; margin-bottom: 20px; }
  .reports-only-banner svg { width: 24px; height: 24px; color: #F59E0B; flex-shrink: 0; }
  .reports-only-text { display: flex; flex-direction: column; gap: 2px; }
  .reports-only-title { font-size: 14px; font-weight: 600; color: #F59E0B; }
  .reports-only-desc { font-size: 12px; color: rgba(255,255,255,0.5); }
  
  /* Event Summary Stats for Past Events */
  .event-summary-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .summary-stat-item { display: flex; flex-direction: column; gap: 4px; padding: 14px; background: rgba(255,255,255,0.03); border-radius: 12px; }
  .summary-stat-label { font-size: 11px; color: rgba(255,255,255,0.5); }
  .summary-stat-value { font-size: 18px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
  
  /* Reports Only View */
  .reports-only-view { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; }
  .reports-only-view .reports-only-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
  .reports-only-view .reports-only-icon svg { width: 40px; height: 40px; color: rgba(255,255,255,0.3); }
  .reports-only-view h3 { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
  .reports-only-view p { font-size: 14px; color: rgba(255,255,255,0.5); max-width: 280px; }
  .reports-only-view .view-reports-btn { display: flex; align-items: center; gap: 8px; margin-top: 20px; padding: 12px 24px; background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 12px; color: #8B5CF6; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .reports-only-view .view-reports-btn svg { width: 18px; height: 18px; }
  .reports-only-view .view-reports-btn:hover { background: #8B5CF6; color: #fff; }
  
  /* Reports Only Mode */
  .reports-only-banner { display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 14px; margin-bottom: 20px; }
  .reports-only-banner svg { width: 24px; height: 24px; color: #8B5CF6; flex-shrink: 0; }
  .reports-only-text { flex: 1; }
  .reports-only-title { display: block; font-size: 13px; font-weight: 600; color: #8B5CF6; margin-bottom: 2px; }
  .reports-only-desc { font-size: 12px; color: rgba(255,255,255,0.5); }
  
  .reports-only-view { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 30px; text-align: center; }
  .reports-only-icon { width: 80px; height: 80px; background: rgba(139, 92, 246, 0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
  .reports-only-icon svg { width: 40px; height: 40px; color: #8B5CF6; }
  .reports-only-view h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
  .reports-only-view p { font-size: 14px; color: rgba(255,255,255,0.5); margin-bottom: 24px; max-width: 280px; }
  .view-reports-btn { display: flex; align-items: center; gap: 10px; padding: 14px 28px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border: none; border-radius: 14px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .view-reports-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4); }
  .view-reports-btn svg { width: 20px; height: 20px; }
  
  .event-summary-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .summary-stat-item { padding: 14px; background: rgba(255,255,255,0.03); border-radius: 12px; }
  .summary-stat-label { display: block; font-size: 11px; color: rgba(255,255,255,0.5); margin-bottom: 4px; }
  .summary-stat-value { font-size: 18px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
  .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 24px; }
  .stat-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 16px; position: relative; overflow: hidden; }
  .stat-card.primary { grid-column: span 2; background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(99, 102, 241, 0.1) 100%); border-color: rgba(139, 92, 246, 0.3); }
  .stat-icon { width: 40px; height: 40px; border-radius: 12px; background: rgba(139, 92, 246, 0.2); display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
  .stat-icon svg { width: 20px; height: 20px; color: #8B5CF6; }
  .stat-icon.cyan { background: rgba(6, 182, 212, 0.2); }
  .stat-icon.cyan svg { color: #06B6D4; }
  .stat-icon.amber { background: rgba(245, 158, 11, 0.2); }
  .stat-icon.amber svg { color: #F59E0B; }
  .stat-icon.green { background: rgba(16, 185, 129, 0.2); }
  .stat-icon.green svg { color: #10B981; }
  .stat-content { display: flex; flex-direction: column; gap: 2px; }
  .stat-value { font-size: 24px; font-weight: 700; color: #fff; font-family: 'JetBrains Mono', monospace; }
  .stat-label { font-size: 12px; color: rgba(255,255,255,0.5); font-weight: 500; }
  .stat-trend { position: absolute; top: 16px; right: 16px; display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: #10B981; }
  .stat-trend svg { width: 14px; height: 14px; }
  .capacity-bar { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 12px; overflow: hidden; }
  .capacity-fill { height: 100%; background: linear-gradient(90deg, #F59E0B, #EF4444); border-radius: 2px; transition: width 0.5s ease; }

  .quick-actions { margin-bottom: 24px; }
  .quick-actions h3 { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 12px; }
  .actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
  .action-btn { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 16px 8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; color: #fff; cursor: pointer; transition: all 0.2s; }
  .action-btn:hover { background: rgba(255,255,255,0.06); transform: translateY(-2px); }
  .action-btn.scan .action-icon { background: rgba(139, 92, 246, 0.2); color: #8B5CF6; }
  .action-btn.sell .action-icon { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .action-btn.guest .action-icon { background: rgba(6, 182, 212, 0.2); color: #06B6D4; }
  .action-btn.staff .action-icon { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
  .action-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
  .action-icon svg { width: 22px; height: 22px; }
  .action-btn span { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.7); }

  .recent-activity { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); }
  .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
  .section-header h3 { font-size: 16px; font-weight: 600; }
  .see-all { background: none; border: none; color: #8B5CF6; font-size: 13px; font-weight: 600; cursor: pointer; }
  .activity-list { display: flex; flex-direction: column; gap: 12px; }
  .activity-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255,255,255,0.02); border-radius: 12px; transition: all 0.2s; }
  .activity-item:hover { background: rgba(255,255,255,0.04); }
  .activity-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #8B5CF6, #6366F1); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; }
  .activity-info { flex: 1; }
  .activity-name { display: block; font-size: 14px; font-weight: 600; margin-bottom: 2px; }
  .activity-meta { font-size: 12px; color: rgba(255,255,255,0.5); }
  .activity-time { font-size: 12px; color: rgba(255,255,255,0.4); font-family: 'JetBrains Mono', monospace; }
  .activity-status { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
  .activity-status.valid { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .activity-status.duplicate { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
  .activity-status svg { width: 14px; height: 14px; }

  .checkin-view { padding: 20px; }
  .scanner-container { display: flex; flex-direction: column; align-items: center; gap: 20px; }
  .scanner-frame { width: 280px; height: 280px; border-radius: 24px; background: rgba(255,255,255,0.02); border: 2px solid rgba(255,255,255,0.1); position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; transition: all 0.3s; }
  .scanner-frame.scanning { border-color: #8B5CF6; box-shadow: 0 0 40px rgba(139, 92, 246, 0.3); }
  .scanner-frame.valid { border-color: #10B981; box-shadow: 0 0 40px rgba(16, 185, 129, 0.3); }
  .scanner-frame.duplicate { border-color: #F59E0B; box-shadow: 0 0 40px rgba(245, 158, 11, 0.3); }
  .scanner-frame.invalid { border-color: #EF4444; box-shadow: 0 0 40px rgba(239, 68, 68, 0.3); }
  .scanner-corners { position: absolute; inset: 20px; }
  .scanner-corners span { position: absolute; width: 30px; height: 30px; border: 3px solid rgba(255,255,255,0.3); }
  .scanner-corners span:nth-child(1) { top: 0; left: 0; border-right: none; border-bottom: none; border-radius: 8px 0 0 0; }
  .scanner-corners span:nth-child(2) { top: 0; right: 0; border-left: none; border-bottom: none; border-radius: 0 8px 0 0; }
  .scanner-corners span:nth-child(3) { bottom: 0; left: 0; border-right: none; border-top: none; border-radius: 0 0 0 8px; }
  .scanner-corners span:nth-child(4) { bottom: 0; right: 0; border-left: none; border-top: none; border-radius: 0 0 8px 0; }
  .scanner-line { position: absolute; left: 20px; right: 20px; height: 2px; background: linear-gradient(90deg, transparent, #8B5CF6, transparent); animation: scan 2s linear infinite; }
  @keyframes scan { 0% { top: 20px; } 50% { top: calc(100% - 22px); } 100% { top: 20px; } }
  .scanner-prompt { display: flex; flex-direction: column; align-items: center; gap: 12px; color: rgba(255,255,255,0.4); }
  .scanner-prompt svg { width: 64px; height: 64px; }
  .scanner-prompt span { font-size: 14px; }
  .scan-result { display: flex; align-items: center; justify-content: center; }
  .result-icon { width: 80px; height: 80px; }
  .scan-result.valid .result-icon { color: #10B981; }
  .scan-result.duplicate .result-icon { color: #F59E0B; }
  .scan-result.invalid .result-icon { color: #EF4444; }
  .result-card { width: 100%; padding: 20px; border-radius: 16px; text-align: center; }
  .result-card.valid { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); }
  .result-card.duplicate { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); }
  .result-card.invalid { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); }
  .result-status { font-size: 12px; font-weight: 700; letter-spacing: 1px; margin-bottom: 12px; }
  .result-card.valid .result-status { color: #10B981; }
  .result-card.duplicate .result-status { color: #F59E0B; }
  .result-card.invalid .result-status { color: #EF4444; }
  .result-name { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .result-ticket { font-size: 14px; color: rgba(255,255,255,0.6); margin-bottom: 4px; }
  .result-seat { font-size: 13px; color: rgba(255,255,255,0.5); margin-bottom: 12px; }
  .result-message { font-size: 14px; color: rgba(255,255,255,0.7); }
  .scan-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 18px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border: none; border-radius: 16px; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .scan-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4); }
  .scan-btn:disabled { opacity: 0.7; cursor: not-allowed; }
  .scan-btn svg { width: 22px; height: 22px; }
  .btn-spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .manual-entry-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: rgba(255,255,255,0.7); font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
  .manual-entry-btn:hover { background: rgba(255,255,255,0.08); color: #fff; }
  .manual-entry-btn svg { width: 18px; height: 18px; }
  .checkin-stats { display: flex; justify-content: center; gap: 16px; padding: 16px 0; }
  .stat-pill { display: flex; align-items: baseline; gap: 4px; padding: 8px 16px; background: rgba(255,255,255,0.05); border-radius: 20px; }
  .pill-value { font-size: 18px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
  .pill-label { font-size: 12px; color: rgba(255,255,255,0.5); }
  .recent-scans { margin-top: 24px; }
  .recent-scans h4 { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 12px; }
  .scans-list { display: flex; flex-direction: column; gap: 8px; }
  .scan-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255,255,255,0.02); border-radius: 12px; }
  .scan-status-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; }
  .scan-status-icon.valid { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .scan-status-icon.duplicate { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
  .scan-info { flex: 1; }
  .scan-name { display: block; font-size: 14px; font-weight: 500; margin-bottom: 2px; }
  .scan-ticket { font-size: 12px; color: rgba(255,255,255,0.5); }
  .scan-time { font-size: 12px; color: rgba(255,255,255,0.4); font-family: 'JetBrains Mono', monospace; }

  .sales-view { padding: 20px; }
  .ticket-selection h3 { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
  .tickets-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; }
  .ticket-card { display: flex; align-items: center; gap: 14px; padding: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; cursor: pointer; transition: all 0.2s; }
  .ticket-card:not(.soldout):hover { background: rgba(255,255,255,0.06); transform: translateX(4px); }
  .ticket-card.soldout { opacity: 0.5; cursor: not-allowed; }
  .ticket-badge { width: 6px; height: 40px; border-radius: 3px; }
  .ticket-info { flex: 1; }
  .ticket-name { display: block; font-size: 15px; font-weight: 600; margin-bottom: 4px; }
  .ticket-price { font-size: 14px; color: rgba(255,255,255,0.6); font-family: 'JetBrains Mono', monospace; }
  .ticket-availability { font-size: 12px; }
  .ticket-availability .available { color: #10B981; }
  .ticket-availability .soldout { color: #EF4444; }
  .add-btn { width: 36px; height: 36px; border-radius: 10px; background: rgba(139, 92, 246, 0.2); border: none; color: #8B5CF6; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
  .add-btn:hover { background: #8B5CF6; color: #fff; }
  .add-btn svg { width: 18px; height: 18px; }
  .sales-history { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); }
  .sales-total { font-size: 14px; font-weight: 600; color: #10B981; font-family: 'JetBrains Mono', monospace; }
  .history-list { display: flex; flex-direction: column; gap: 12px; }
  .history-item { display: flex; align-items: center; gap: 12px; }
  .sale-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; }
  .sale-icon svg { width: 18px; height: 18px; color: rgba(255,255,255,0.5); }
  .sale-info { flex: 1; }
  .sale-desc { display: block; font-size: 14px; font-weight: 500; margin-bottom: 2px; }
  .sale-time { font-size: 12px; color: rgba(255,255,255,0.4); }
  .sale-amount { font-size: 14px; font-weight: 600; color: #10B981; font-family: 'JetBrains Mono', monospace; }
  .cart-fab { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 12px; padding: 14px 24px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border-radius: 20px; cursor: pointer; box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4); z-index: 50; transition: all 0.3s; }
  .cart-fab:hover { transform: translateX(-50%) translateY(-4px); }
  .fab-badge { position: absolute; top: -8px; left: -8px; width: 24px; height: 24px; border-radius: 50%; background: #EF4444; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
  .cart-fab svg { width: 22px; height: 22px; }
  .cart-fab span { font-size: 16px; font-weight: 600; }

  .cart-view { padding: 20px; }
  .cart-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
  .back-btn { width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
  .back-btn svg { width: 20px; height: 20px; }
  .cart-header h3 { flex: 1; font-size: 20px; }
  .cart-count { font-size: 14px; color: rgba(255,255,255,0.5); }
  .cart-items { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
  .cart-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(255,255,255,0.03); border-radius: 16px; }
  .item-color { width: 4px; height: 40px; border-radius: 2px; }
  .item-details { flex: 1; }
  .item-name { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
  .item-price { font-size: 13px; color: rgba(255,255,255,0.5); }
  .item-quantity { display: flex; align-items: center; gap: 12px; }
  .item-quantity button { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.1); border: none; color: #fff; font-size: 18px; cursor: pointer; transition: all 0.2s; }
  .item-quantity button:hover { background: rgba(255,255,255,0.2); }
  .item-quantity span { font-size: 16px; font-weight: 600; min-width: 24px; text-align: center; }
  .item-total { font-size: 15px; font-weight: 600; font-family: 'JetBrains Mono', monospace; min-width: 80px; text-align: right; }
  .cart-summary { padding: 20px; background: rgba(255,255,255,0.03); border-radius: 16px; margin-bottom: 24px; }
  .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: rgba(255,255,255,0.6); }
  .summary-row.total { border-top: 1px solid rgba(255,255,255,0.1); margin-top: 8px; padding-top: 16px; font-size: 18px; font-weight: 700; color: #fff; }
  .payment-methods h4 { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 12px; }
  .methods-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .method-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 24px; background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.1); border-radius: 16px; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; position: relative; }
  .method-btn:hover:not(:disabled) { border-color: #8B5CF6; background: rgba(139, 92, 246, 0.1); }
  .method-btn.active { border-color: #8B5CF6; background: rgba(139, 92, 246, 0.15); }
  .method-btn:disabled { opacity: 0.7; cursor: not-allowed; }
  .method-btn svg { width: 32px; height: 32px; }
  .method-spinner { position: absolute; top: 12px; right: 12px; width: 16px; height: 16px; border: 2px solid rgba(139, 92, 246, 0.3); border-top-color: #8B5CF6; border-radius: 50%; animation: spin 0.8s linear infinite; }
  .payment-success { position: fixed; inset: 0; background: rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center; z-index: 200; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  .success-content { display: flex; flex-direction: column; align-items: center; gap: 16px; animation: scaleIn 0.4s ease; }
  @keyframes scaleIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  .success-content svg { width: 80px; height: 80px; color: #10B981; }
  .success-content span { font-size: 20px; font-weight: 600; }
  .success-amount { font-size: 32px !important; font-weight: 700 !important; font-family: 'JetBrains Mono', monospace; color: #10B981; }

  .reports-view { padding: 20px; }
  .reports-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .reports-header h3 { font-size: 20px; font-weight: 600; }
  .report-time { display: flex; align-items: center; gap: 8px; font-size: 12px; color: rgba(255,255,255,0.5); }
  .pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #10B981; animation: pulse 2s infinite; }
  .metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 24px; }
  .metric-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 16px; }
  .metric-card.large { grid-column: span 2; }
  .metric-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
  .metric-label { font-size: 12px; color: rgba(255,255,255,0.5); display: block; margin-bottom: 4px; }
  .metric-value { font-size: 28px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
  .metric-trend { font-size: 12px; font-weight: 600; color: #10B981; }
  .mini-chart { margin-top: 12px; height: 50px; }
  .chart-line { width: 100%; height: 100%; }
  .report-section { margin-bottom: 24px; }
  .report-section h4 { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 16px; }
  .gates-list { display: flex; flex-direction: column; gap: 16px; }
  .gate-item { display: flex; align-items: center; gap: 12px; }
  .gate-info { width: 100px; }
  .gate-name { display: block; font-size: 14px; font-weight: 500; margin-bottom: 2px; }
  .gate-scans { font-size: 11px; color: rgba(255,255,255,0.4); }
  .gate-bar { flex: 1; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; }
  .gate-progress { height: 100%; background: linear-gradient(90deg, #8B5CF6, #06B6D4); border-radius: 4px; transition: width 0.5s ease; }
  .gate-percent { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.7); width: 40px; text-align: right; font-family: 'JetBrains Mono', monospace; }
  .revenue-chart { display: flex; flex-direction: column; gap: 14px; }
  .revenue-bar { display: flex; align-items: center; gap: 12px; }
  .revenue-label { display: flex; align-items: center; gap: 8px; width: 140px; font-size: 13px; }
  .revenue-dot { width: 10px; height: 10px; border-radius: 3px; }
  .revenue-track { flex: 1; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
  .revenue-fill { height: 100%; border-radius: 3px; }
  .revenue-amount { font-size: 12px; color: rgba(255,255,255,0.6); width: 90px; text-align: right; font-family: 'JetBrains Mono', monospace; }
  .hourly-chart { display: flex; align-items: flex-end; justify-content: space-between; height: 120px; padding: 0 10px; }
  .hour-bar { display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1; }
  .bar-fill { width: 24px; background: linear-gradient(180deg, #8B5CF6, rgba(139, 92, 246, 0.3)); border-radius: 4px 4px 0 0; transition: height 0.5s ease; }
  .hour-label { font-size: 10px; color: rgba(255,255,255,0.4); }
  .export-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .export-btn:hover { background: rgba(255,255,255,0.1); }
  .export-btn svg { width: 20px; height: 20px; }

  .settings-view { padding: 20px; }
  .settings-header h3 { font-size: 20px; font-weight: 600; margin-bottom: 24px; }
  .settings-section { margin-bottom: 28px; }
  .settings-section h4 { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
  .setting-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(255,255,255,0.02); border-radius: 14px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; }
  .setting-item:hover { background: rgba(255,255,255,0.04); }
  .setting-info { flex: 1; }
  .setting-label { display: block; font-size: 15px; font-weight: 500; margin-bottom: 2px; }
  .setting-value { font-size: 13px; color: rgba(255,255,255,0.5); }
  .setting-value.connected { color: #10B981; }
  .setting-desc { font-size: 12px; color: rgba(255,255,255,0.4); }
  .setting-arrow { width: 20px; height: 20px; color: rgba(255,255,255,0.3); }
  .toggle-switch { width: 48px; height: 28px; border-radius: 14px; background: rgba(255,255,255,0.1); padding: 2px; cursor: pointer; transition: all 0.3s; }
  .toggle-switch.active { background: #8B5CF6; }
  .toggle-thumb { width: 24px; height: 24px; border-radius: 12px; background: #fff; transition: all 0.3s; }
  .toggle-switch.active .toggle-thumb { transform: translateX(20px); }
  .offline-info { display: flex; align-items: center; gap: 10px; padding: 14px; background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.2); border-radius: 12px; margin-top: 12px; }
  .offline-info svg { width: 18px; height: 18px; color: #06B6D4; }
  .offline-info span { font-size: 13px; color: rgba(255,255,255,0.7); }
  .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 14px; color: #EF4444; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 20px; }
  .logout-btn:hover { background: rgba(239, 68, 68, 0.2); }
  .logout-btn svg { width: 20px; height: 20px; }

  .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 430px; display: flex; justify-content: space-around; padding: 12px 20px 28px; background: linear-gradient(180deg, rgba(10, 10, 15, 0) 0%, rgba(10, 10, 15, 1) 20%); backdrop-filter: blur(20px); z-index: 100; }
  .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 16px; background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; transition: all 0.2s; border-radius: 12px; }
  .nav-item:hover { color: rgba(255,255,255,0.6); }
  .nav-item.active { color: #8B5CF6; background: rgba(139, 92, 246, 0.1); }
  .nav-item svg { width: 24px; height: 24px; }
  .nav-item span { font-size: 11px; font-weight: 500; }

  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: flex-end; justify-content: center; z-index: 150; animation: fadeIn 0.2s ease; }
  .modal-content { width: 100%; max-width: 430px; max-height: 85vh; background: #15151F; border-radius: 24px 24px 0 0; padding: 24px; animation: slideUp 0.3s ease; overflow-y: auto; }
  .modal-content.large { max-height: 90vh; }
  .modal-content.events-modal { max-height: 90vh; padding-bottom: 40px; }
  @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .modal-header h3 { font-size: 18px; font-weight: 600; }
  .close-btn { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.05); border: none; color: rgba(255,255,255,0.6); cursor: pointer; display: flex; align-items: center; justify-content: center; }
  .close-btn svg { width: 18px; height: 18px; }

  /* Events Modal */
  .events-list-container { display: flex; flex-direction: column; gap: 24px; }
  .events-group { display: flex; flex-direction: column; gap: 8px; }
  .events-group-header { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 0; color: rgba(255,255,255,0.5); }
  .events-group-header.live { color: #10B981; }
  .events-group-header.today { color: #06B6D4; }
  .events-group-header.past { color: rgba(255,255,255,0.4); }
  .events-group-header.future { color: rgba(255,255,255,0.3); }
  
  .event-list-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; cursor: pointer; transition: all 0.2s; position: relative; }
  .event-list-item:hover { background: rgba(255,255,255,0.06); }
  .event-list-item.selected { background: rgba(139, 92, 246, 0.1); border-color: rgba(139, 92, 246, 0.3); }
  .event-list-item.live { border-color: rgba(16, 185, 129, 0.3); }
  .event-list-item.locked { opacity: 0.5; cursor: not-allowed; }
  .event-list-item.locked:hover { background: rgba(255,255,255,0.03); }
  
  .event-list-info { flex: 1; min-width: 0; }
  .event-list-name { font-size: 14px; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .event-list-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: rgba(255,255,255,0.5); }
  .event-list-date { color: rgba(255,255,255,0.4); }
  
  .event-list-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .event-list-status { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
  .event-list-status.live { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .event-list-status.ended { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }
  .event-list-status.today { background: rgba(6, 182, 212, 0.2); color: #06B6D4; }
  
  .event-list-locked { display: flex; align-items: center; gap: 6px; font-size: 11px; color: rgba(255,255,255,0.4); }
  .event-list-locked svg { width: 14px; height: 14px; }
  
  .event-list-check { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 24px; height: 24px; background: #8B5CF6; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
  .event-list-check svg { width: 14px; height: 14px; color: #fff; }
  
  .pulse-small { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: pulse 1.5s infinite; }
  
  .future-events-notice { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 10px; margin-bottom: 8px; font-size: 12px; color: rgba(255,255,255,0.4); }
  .future-events-notice svg { width: 16px; height: 16px; flex-shrink: 0; }

  .staff-list { display: flex; flex-direction: column; gap: 12px; }
  .staff-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(255,255,255,0.03); border-radius: 14px; }
  .staff-avatar { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #F59E0B, #EF4444); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; flex-shrink: 0; }
  .staff-info { flex: 1; }
  .staff-name { display: block; font-size: 15px; font-weight: 600; margin-bottom: 2px; }
  .staff-role { font-size: 12px; color: rgba(255,255,255,0.5); }
  .staff-stats { font-size: 12px; color: rgba(255,255,255,0.5); text-align: right; }
  .staff-status { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
  .staff-status.active { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .staff-status.break { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }

  .staff-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
  .staff-summary-item { text-align: center; padding: 12px; background: rgba(255,255,255,0.02); border-radius: 12px; }
  .staff-summary-value { display: block; font-size: 24px; font-weight: 700; color: #8B5CF6; font-family: 'JetBrains Mono', monospace; }
  .staff-summary-label { font-size: 11px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }

  .staff-list.detailed { gap: 16px; }
  .staff-item-detailed { padding: 16px; background: rgba(255,255,255,0.02); border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); }
  .staff-item-detailed.break { opacity: 0.6; }
  .staff-item-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
  .staff-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); }
  .staff-detail { display: flex; justify-content: space-between; padding: 6px 10px; background: rgba(255,255,255,0.02); border-radius: 8px; }
  .detail-label { font-size: 11px; color: rgba(255,255,255,0.4); }
  .detail-value { font-size: 12px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
  .detail-value.highlight { color: #8B5CF6; }
  .detail-value.cash { color: #10B981; }
  .detail-value.card { color: #06B6D4; }

  .search-bar { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: rgba(255,255,255,0.05); border-radius: 14px; margin-bottom: 20px; }
  .search-bar svg { width: 20px; height: 20px; color: rgba(255,255,255,0.4); }
  .search-bar input { flex: 1; background: none; border: none; outline: none; color: #fff; font-size: 15px; }
  .search-bar input::placeholder { color: rgba(255,255,255,0.3); }
  .guest-list { display: flex; flex-direction: column; gap: 10px; }
  .guest-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(255,255,255,0.03); border-radius: 14px; }
  .guest-item.checked { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
  .guest-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #06B6D4, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
  .guest-info { flex: 1; }
  .guest-name { display: block; font-size: 14px; font-weight: 600; margin-bottom: 2px; }
  .guest-type { font-size: 12px; color: rgba(255,255,255,0.5); }
  .check-btn { padding: 8px 16px; border-radius: 8px; background: rgba(139, 92, 246, 0.2); border: none; color: #8B5CF6; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .check-btn:hover { background: #8B5CF6; color: #fff; }
  .check-btn.checked { background: rgba(16, 185, 129, 0.2); color: #10B981; padding: 8px; }
  .check-btn.checked svg { width: 16px; height: 16px; }

  .manual-form { display: flex; flex-direction: column; gap: 20px; }
  .form-group label { display: block; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.6); margin-bottom: 8px; }
  .form-group input { width: 100%; padding: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 15px; outline: none; transition: all 0.2s; }
  .form-group input:focus { border-color: #8B5CF6; background: rgba(139, 92, 246, 0.1); }
  .form-group input::placeholder { color: rgba(255,255,255,0.3); }
  .submit-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 18px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border: none; border-radius: 14px; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4); }
  .submit-btn svg { width: 20px; height: 20px; }

  .notifications-panel { position: fixed; inset: 0; z-index: 160; }
  .notifications-content { position: absolute; top: 70px; right: 20px; width: 320px; max-height: 400px; background: #1A1A24; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 60px rgba(0,0,0,0.5); overflow: hidden; animation: slideDown 0.2s ease; }
  @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
  .notifications-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
  .notifications-header h3 { font-size: 15px; font-weight: 600; }
  .mark-read { background: none; border: none; color: #8B5CF6; font-size: 12px; font-weight: 600; cursor: pointer; }
  .notifications-list { max-height: 320px; overflow-y: auto; }
  .notification-item { display: flex; gap: 12px; padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); transition: all 0.2s; }
  .notification-item:hover { background: rgba(255,255,255,0.02); }
  .notification-item.unread { background: rgba(139, 92, 246, 0.05); }
  .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
  .notification-item.alert .notif-icon { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
  .notification-item.info .notif-icon { background: rgba(6, 182, 212, 0.2); color: #06B6D4; }
  .notification-item.success .notif-icon { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .notif-icon svg { width: 18px; height: 18px; }
  .notif-content { flex: 1; }
  .notif-message { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; line-height: 1.4; }
  .notif-time { font-size: 11px; color: rgba(255,255,255,0.4); }

  /* Stripe Tap to Pay Button */
  .method-btn.tap-to-pay { flex-direction: column; padding: 20px 16px; }
  .method-btn.tap-to-pay .tap-icon { position: relative; margin-bottom: 8px; }
  .method-btn.tap-to-pay .tap-icon svg { width: 32px; height: 32px; }
  .contactless-waves { position: absolute; top: -4px; right: -8px; display: flex; flex-direction: column; gap: 2px; }
  .contactless-waves span { width: 8px; height: 8px; border: 2px solid #8B5CF6; border-radius: 50%; border-left-color: transparent; border-bottom-color: transparent; transform: rotate(45deg); opacity: 0.6; }
  .contactless-waves span:nth-child(2) { width: 12px; height: 12px; margin-left: -2px; margin-top: -2px; }
  .contactless-waves span:nth-child(3) { width: 16px; height: 16px; margin-left: -4px; margin-top: -4px; }
  .powered-by { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 4px; }

  /* Email Capture */
  .success-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; width: 100%; }
  .email-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 20px; background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 12px; color: #8B5CF6; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .email-btn:hover { background: #8B5CF6; color: #fff; }
  .email-btn svg { width: 18px; height: 18px; }
  .skip-btn { padding: 12px; background: none; border: none; color: rgba(255,255,255,0.5); font-size: 14px; cursor: pointer; }
  
  .email-capture-modal { position: absolute; inset: 0; background: rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 10; }
  .email-capture-content { width: 100%; max-width: 340px; padding: 24px; background: #1A1A24; border-radius: 20px; text-align: center; }
  .email-capture-header { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 12px; }
  .email-capture-header svg { width: 48px; height: 48px; color: #8B5CF6; }
  .email-capture-header h4 { font-size: 18px; font-weight: 600; }
  .email-capture-desc { font-size: 14px; color: rgba(255,255,255,0.5); margin-bottom: 20px; }
  .email-input-group { margin-bottom: 20px; }
  .email-input-group input { width: 100%; padding: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 15px; text-align: center; outline: none; }
  .email-input-group input:focus { border-color: #8B5CF6; }
  .email-capture-actions { display: flex; flex-direction: column; gap: 10px; }
  .send-email-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border: none; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
  .send-email-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .send-email-btn svg { width: 18px; height: 18px; }
  .btn-spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
  .cancel-email-btn { padding: 12px; background: none; border: none; color: rgba(255,255,255,0.5); font-size: 14px; cursor: pointer; }

  /* Admin Sections in Settings */
  .admin-section { border: 1px solid rgba(139, 92, 246, 0.2); background: rgba(139, 92, 246, 0.05); }
  .admin-badge { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #8B5CF6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
  .admin-badge svg { width: 16px; height: 16px; }
  .setting-item.clickable { cursor: pointer; transition: all 0.2s; }
  .setting-item.clickable:hover { background: rgba(255,255,255,0.03); }
  .setting-badge { padding: 4px 10px; background: rgba(139, 92, 246, 0.2); border-radius: 6px; font-size: 12px; font-weight: 600; color: #8B5CF6; margin-right: 8px; }

  /* Gate Manager Modal */
  .gate-form { padding: 16px; background: rgba(255,255,255,0.02); border-radius: 14px; margin-bottom: 20px; }
  .gate-form h4 { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 12px; }
  .gate-form-row { display: flex; gap: 10px; margin-bottom: 10px; }
  .gate-form-row input, .gate-form-row select { flex: 1; padding: 12px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: #fff; font-size: 14px; outline: none; }
  .gate-form-row input:focus, .gate-form-row select:focus { border-color: #8B5CF6; }
  .gate-form-row select { appearance: none; cursor: pointer; }
  .add-gate-btn { display: flex; align-items: center; gap: 6px; padding: 12px 16px; background: #8B5CF6; border: none; border-radius: 10px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
  .add-gate-btn svg { width: 16px; height: 16px; }
  
  .gates-list h4 { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 12px; }
  .gate-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(255,255,255,0.02); border-radius: 12px; margin-bottom: 8px; }
  .gate-item.inactive { opacity: 0.5; }
  .gate-icon { width: 40px; height: 40px; background: rgba(139, 92, 246, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
  .gate-icon svg { width: 20px; height: 20px; color: #8B5CF6; }
  .gate-info { flex: 1; }
  .gate-name { display: block; font-size: 14px; font-weight: 600; }
  .gate-location { font-size: 12px; color: rgba(255,255,255,0.5); }
  .gate-type-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
  .gate-type-badge.entry { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .gate-type-badge.vip { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
  .gate-type-badge.pos { background: rgba(6, 182, 212, 0.2); color: #06B6D4; }
  .gate-type-badge.exit { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
  .gate-actions { display: flex; gap: 8px; }
  .toggle-gate-btn { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; }
  .toggle-gate-btn.active { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .toggle-gate-btn:not(.active) { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }
  .remove-gate-btn { width: 32px; height: 32px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 8px; color: #EF4444; cursor: pointer; display: flex; align-items: center; justify-content: center; }
  .remove-gate-btn svg { width: 16px; height: 16px; }

  /* Staff Assignment Modal */
  .assignment-form { padding: 16px; background: rgba(255,255,255,0.02); border-radius: 14px; margin-bottom: 20px; }
  .assignment-form h4 { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 12px; }
  .assignment-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .assignment-form .form-group { display: flex; flex-direction: column; gap: 6px; }
  .assignment-form .form-group label { font-size: 11px; color: rgba(255,255,255,0.5); }
  .assignment-form .form-group input, .assignment-form .form-group select { padding: 10px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 14px; outline: none; }
  .assignment-form .form-group input:focus, .assignment-form .form-group select:focus { border-color: #8B5CF6; }
  .add-assignment-btn { grid-column: span 2; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px; background: #8B5CF6; border: none; border-radius: 10px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
  .add-assignment-btn svg { width: 16px; height: 16px; }
  
  .assignments-list h4 { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 12px; }
  .assignment-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(255,255,255,0.02); border-radius: 12px; margin-bottom: 8px; }
  .assignment-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #8B5CF6, #6366F1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
  .assignment-info { flex: 1; min-width: 0; }
  .assignment-name { display: block; font-size: 14px; font-weight: 600; }
  .assignment-gate { font-size: 12px; color: rgba(255,255,255,0.5); }
  .assignment-schedule { display: flex; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,0.6); font-family: 'JetBrains Mono', monospace; }
  .assignment-schedule svg { width: 14px; height: 14px; }
  .role-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
  .role-badge.scanner { background: rgba(16, 185, 129, 0.2); color: #10B981; }
  .role-badge.pos { background: rgba(6, 182, 212, 0.2); color: #06B6D4; }
  .role-badge.supervisor { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
  .remove-assignment-btn { width: 32px; height: 32px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 8px; color: #EF4444; cursor: pointer; display: flex; align-items: center; justify-content: center; }
  .remove-assignment-btn svg { width: 16px; height: 16px; }
`;

export default function TixelloApp() {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [activeView, setActiveView] = useState(null);
  const [scanResult, setScanResult] = useState(null);
  const [isScanning, setIsScanning] = useState(false);
  const [cartItems, setCartItems] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState(null);
  const [showPaymentSuccess, setShowPaymentSuccess] = useState(false);
  const [notifications, setNotifications] = useState([
    { id: 1, type: 'alert', message: 'VIP gate running low on wristbands', time: '2m ago', unread: true },
    { id: 2, type: 'info', message: '500 check-ins milestone reached!', time: '15m ago', unread: true },
    { id: 3, type: 'success', message: 'Card reader reconnected', time: '1h ago', unread: false },
  ]);
  const [showNotifications, setShowNotifications] = useState(false);
  const [offlineMode, setOfflineMode] = useState(false);
  
  // Scanner settings
  const [vibrationFeedback, setVibrationFeedback] = useState(true);
  const [soundEffects, setSoundEffects] = useState(true);
  const [autoConfirmValid, setAutoConfirmValid] = useState(false);
  
  // Email capture after payment
  const [showEmailCapture, setShowEmailCapture] = useState(false);
  const [buyerEmail, setBuyerEmail] = useState('');
  const [sendingEmail, setSendingEmail] = useState(false);
  const [lastSaleData, setLastSaleData] = useState(null);
  
  // Admin: Gate Management
  const [showGateManager, setShowGateManager] = useState(false);
  const [gates, setGates] = useState([
    { id: 1, name: 'Gate A', type: 'entry', location: 'North Entrance', active: true },
    { id: 2, name: 'Gate B', type: 'entry', location: 'South Entrance', active: true },
    { id: 3, name: 'VIP Entrance', type: 'vip', location: 'East Wing', active: true },
    { id: 4, name: 'Box Office 1', type: 'pos', location: 'Main Hall', active: true },
    { id: 5, name: 'Box Office 2', type: 'pos', location: 'West Wing', active: true },
  ]);
  
  // Admin: Staff Assignment & Scheduling
  const [showStaffAssignment, setShowStaffAssignment] = useState(false);
  const [staffSchedules, setStaffSchedules] = useState([
    { id: 1, staffId: 1, staffName: 'Alexandru M.', gateId: 1, gateName: 'Gate A', shiftStart: '18:00', shiftEnd: '02:00', role: 'scanner' },
    { id: 2, staffId: 2, staffName: 'Ioana P.', gateId: 2, gateName: 'Gate B', shiftStart: '18:00', shiftEnd: '02:00', role: 'scanner' },
    { id: 3, staffId: 3, staffName: 'Cristian D.', gateId: 4, gateName: 'Box Office 1', shiftStart: '17:30', shiftEnd: '01:00', role: 'pos' },
    { id: 4, staffId: 5, staffName: 'Mihai V.', gateId: 3, gateName: 'VIP Entrance', shiftStart: '18:00', shiftEnd: '02:00', role: 'scanner' },
    { id: 5, staffId: 6, staffName: 'Elena S.', gateId: 5, gateName: 'Box Office 2', shiftStart: '17:30', shiftEnd: '01:00', role: 'pos' },
  ]);
  
  // Current user role (for conditional UI)
  const [currentUserRole, setCurrentUserRole] = useState('admin'); // 'admin' | 'scanner' | 'pos'
  const cachedTickets = 12456;

  const [showEventsModal, setShowEventsModal] = useState(false);

  // Mock Data - Events with different time categories
  // Categories: 'live' (happening now), 'today' (later today), 'past' (already happened), 'future' (upcoming)
  const events = [
    // Live now
    { id: 1, name: 'Summer Music Festival 2025', date: 'Dec 27, 2025', dateTime: '2025-12-27T18:00', venue: 'Arena Națională', capacity: 50000, checkedIn: 34567, sold: 48234, revenue: 4823400, status: 'live', timeCategory: 'live' },
    
    // Today (later)
    { id: 2, name: 'Jazz Night Bucharest', date: 'Dec 27, 2025', dateTime: '2025-12-27T21:00', venue: 'Green Hours', capacity: 200, checkedIn: 0, sold: 187, revenue: 28050, status: 'upcoming', timeCategory: 'today' },
    
    // Past events
    { id: 3, name: 'Christmas Concert 2025', date: 'Dec 25, 2025', dateTime: '2025-12-25T19:00', venue: 'Sala Palatului', capacity: 4000, checkedIn: 3847, sold: 3892, revenue: 778400, status: 'ended', timeCategory: 'past' },
    { id: 4, name: 'Winter Gala', date: 'Dec 20, 2025', dateTime: '2025-12-20T20:00', venue: 'Ateneul Român', capacity: 800, checkedIn: 756, sold: 780, revenue: 312000, status: 'ended', timeCategory: 'past' },
    { id: 5, name: 'Rock Festival Day 1', date: 'Dec 15, 2025', dateTime: '2025-12-15T16:00', venue: 'Romexpo', capacity: 15000, checkedIn: 14234, sold: 14500, revenue: 2175000, status: 'ended', timeCategory: 'past' },
    
    // Tomorrow
    { id: 6, name: 'Comedy Night Bucharest', date: 'Dec 28, 2025', dateTime: '2025-12-28T20:00', venue: 'Club Control', capacity: 500, checkedIn: 0, sold: 423, revenue: 63450, status: 'upcoming', timeCategory: 'future' },
    
    // Next week
    { id: 7, name: 'New Year Eve Party', date: 'Dec 31, 2025', dateTime: '2025-12-31T22:00', venue: 'Berăria H', capacity: 2000, checkedIn: 0, sold: 1654, revenue: 413500, status: 'upcoming', timeCategory: 'future' },
    { id: 8, name: 'Electronic Music Night', date: 'Jan 3, 2026', dateTime: '2026-01-03T23:00', venue: 'Kristal Glam Club', capacity: 1000, checkedIn: 0, sold: 234, revenue: 58500, status: 'upcoming', timeCategory: 'future' },
    
    // Next month
    { id: 9, name: 'Tech Conference Romania', date: 'Jan 15, 2026', dateTime: '2026-01-15T09:00', venue: 'Palatul Parlamentului', capacity: 2000, checkedIn: 0, sold: 1856, revenue: 927800, status: 'upcoming', timeCategory: 'future' },
    { id: 10, name: 'Valentine Concert', date: 'Feb 14, 2026', dateTime: '2026-02-14T19:00', venue: 'Sala Palatului', capacity: 4000, checkedIn: 0, sold: 890, revenue: 222500, status: 'upcoming', timeCategory: 'future' },
    
    // Far future
    { id: 11, name: 'Summer Festival 2026', date: 'Jul 15, 2026', dateTime: '2026-07-15T14:00', venue: 'Arena Națională', capacity: 50000, checkedIn: 0, sold: 12500, revenue: 1875000, status: 'upcoming', timeCategory: 'future' },
  ];

  const ticketTypes = [
    { id: 1, name: 'General Admission', price: 150, available: 234, color: '#8B5CF6' },
    { id: 2, name: 'VIP Access', price: 450, available: 45, color: '#F59E0B' },
    { id: 3, name: 'Early Bird', price: 100, available: 0, color: '#10B981' },
    { id: 4, name: 'Student', price: 75, available: 89, color: '#06B6D4' },
  ];

  const recentScans = [
    { id: 1, name: 'Maria Ionescu', ticket: 'VIP Access', time: '10:32', status: 'valid', zone: 'Gate A' },
    { id: 2, name: 'Andrei Popescu', ticket: 'General', time: '10:31', status: 'valid', zone: 'Gate A' },
    { id: 3, name: 'Elena Dumitrescu', ticket: 'General', time: '10:30', status: 'duplicate', zone: 'Gate A' },
    { id: 4, name: 'Mihai Constantinescu', ticket: 'Student', time: '10:28', status: 'valid', zone: 'Gate A' },
  ];

  const salesHistory = [
    { id: 1, tickets: 2, type: 'General Admission', total: 300, method: 'card', time: '10:45' },
    { id: 2, tickets: 1, type: 'VIP Access', total: 450, method: 'cash', time: '10:38' },
    { id: 3, tickets: 4, type: 'Student', total: 300, method: 'card', time: '10:22' },
    { id: 4, tickets: 1, type: 'General Admission', total: 150, method: 'cash', time: '10:15' },
  ];

  const liveStats = {
    checkInsPerMinute: 45,
    salesPerMinute: 12,
    currentCapacity: 69,
    peakHour: '19:00',
    avgWaitTime: '2.3 min',
    topGate: 'Gate B',
  };

  const staffMembers = [
    { id: 1, name: 'Alexandru M.', role: 'Gate Staff', gate: 'Gate A', scans: 234, sales: 0, status: 'active', shiftStart: '18:00', cashCollected: 0, cardCollected: 0, lastActive: '2 min ago' },
    { id: 2, name: 'Ioana P.', role: 'Gate Staff', gate: 'Gate B', scans: 312, sales: 0, status: 'active', shiftStart: '18:00', cashCollected: 0, cardCollected: 0, lastActive: '1 min ago' },
    { id: 3, name: 'Cristian D.', role: 'POS Sales', gate: 'Box Office', scans: 0, sales: 45, status: 'active', shiftStart: '17:30', cashCollected: 3450, cardCollected: 8200, lastActive: 'Just now' },
    { id: 4, name: 'Diana R.', role: 'Supervisor', gate: 'All', scans: 0, sales: 0, status: 'break', shiftStart: '16:00', cashCollected: 0, cardCollected: 0, lastActive: '15 min ago' },
    { id: 5, name: 'Mihai V.', role: 'Gate Staff', gate: 'VIP Entrance', scans: 89, sales: 0, status: 'active', shiftStart: '18:00', cashCollected: 0, cardCollected: 0, lastActive: '3 min ago' },
    { id: 6, name: 'Elena S.', role: 'POS Sales', gate: 'Box Office 2', scans: 0, sales: 32, status: 'active', shiftStart: '17:30', cashCollected: 2100, cardCollected: 5600, lastActive: '5 min ago' },
  ];

  useEffect(() => {
    if (!selectedEvent) {
      // Select first live event, then today, then past (never future as default)
      const liveEvent = events.find(e => e.timeCategory === 'live');
      const todayEvent = events.find(e => e.timeCategory === 'today');
      const pastEvent = events.find(e => e.timeCategory === 'past');
      setSelectedEvent(liveEvent || todayEvent || pastEvent || events[0]);
    }
  }, []);
  
  // Check if current event is past (reports only mode)
  const isReportsOnlyMode = selectedEvent?.timeCategory === 'past';

  // Simulate scanning
  const handleScan = () => {
    setIsScanning(true);
    setScanResult(null);
    
    setTimeout(() => {
      const results = [
        { status: 'valid', name: 'Alexandru Marin', ticket: 'VIP Access', seat: 'Section A, Row 3', message: 'Welcome! Enjoy the show.' },
        { status: 'valid', name: 'Maria Popescu', ticket: 'General Admission', seat: null, message: 'Access granted.' },
        { status: 'duplicate', name: 'Ion Georgescu', ticket: 'General', seat: null, message: 'Ticket already scanned at 18:45' },
        { status: 'invalid', name: null, ticket: null, seat: null, message: 'Invalid QR code' },
      ];
      setScanResult(results[Math.floor(Math.random() * results.length)]);
      setIsScanning(false);
    }, 1500);
  };

  const addToCart = (ticket) => {
    const existing = cartItems.find(item => item.id === ticket.id);
    if (existing) {
      setCartItems(cartItems.map(item => 
        item.id === ticket.id ? { ...item, quantity: item.quantity + 1 } : item
      ));
    } else {
      setCartItems([...cartItems, { ...ticket, quantity: 1 }]);
    }
  };

  const updateQuantity = (ticketId, delta) => {
    setCartItems(cartItems.map(item => {
      if (item.id === ticketId) {
        const newQty = Math.max(0, item.quantity + delta);
        return newQty === 0 ? null : { ...item, quantity: newQty };
      }
      return item;
    }).filter(Boolean));
  };

  const cartTotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const cartCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);

  const processPayment = (method) => {
    setPaymentMethod(method);
    setTimeout(() => {
      setShowPaymentSuccess(true);
      setTimeout(() => {
        setShowPaymentSuccess(false);
        setPaymentMethod(null);
        setCartItems([]);
        setActiveView(null);
      }, 2500);
    }, 2000);
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('ro-RO', { style: 'decimal', minimumFractionDigits: 0 }).format(amount) + ' lei';
  };

  // Header Component
  const Header = () => (
    <div className="header">
      <div className="header-left">
        <div className="logo">
          <svg viewBox="0 0 32 32" fill="none" className="logo-icon">
            <rect width="32" height="32" rx="8" fill="url(#logoGrad)"/>
            <path d="M8 12h16M8 16h12M8 20h8" stroke="white" strokeWidth="2" strokeLinecap="round"/>
            <defs>
              <linearGradient id="logoGrad" x1="0" y1="0" x2="32" y2="32">
                <stop stopColor="#8B5CF6"/>
                <stop offset="1" stopColor="#6366F1"/>
              </linearGradient>
            </defs>
          </svg>
          <span className="logo-text">Tixello</span>
        </div>
      </div>
      <div className="header-right">
        <div className={`connection-status ${offlineMode ? 'offline' : 'online'}`}>
          <span className="status-dot"></span>
          <span className="status-text">{offlineMode ? 'Offline' : 'Live'}</span>
        </div>
        <button className="notification-btn" onClick={() => setShowNotifications(!showNotifications)}>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          {notifications.filter(n => n.unread).length > 0 && (
            <span className="notification-badge">{notifications.filter(n => n.unread).length}</span>
          )}
        </button>
      </div>
    </div>
  );

  // Event Selector
  const EventSelector = () => (
    <div className="event-selector" onClick={() => setShowEventsModal(true)}>
      <div className="event-info">
        <span className="event-name">{selectedEvent?.name}</span>
        <span className="event-meta">{selectedEvent?.date} • {selectedEvent?.venue}</span>
      </div>
      <div className={`event-status ${selectedEvent?.status}`}>
        {selectedEvent?.status === 'live' && <span className="pulse"></span>}
        {selectedEvent?.status === 'live' ? 'LIVE' : selectedEvent?.status === 'ended' ? 'Ended' : 'Upcoming'}
      </div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="chevron">
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </div>
  );

  // Events Modal - Shows all events grouped by category
  const EventsModal = () => {
    const liveEvents = events.filter(e => e.timeCategory === 'live');
    const todayEvents = events.filter(e => e.timeCategory === 'today');
    const pastEvents = events.filter(e => e.timeCategory === 'past');
    const futureEvents = events.filter(e => e.timeCategory === 'future');
    
    const handleSelectEvent = (event) => {
      if (event.timeCategory === 'future') {
        return; // Can't select future events
      }
      setSelectedEvent(event);
      setShowEventsModal(false);
    };
    
    const getRelativeDate = (dateTime) => {
      const eventDate = new Date(dateTime);
      const today = new Date();
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1);
      
      const diffDays = Math.ceil((eventDate - today) / (1000 * 60 * 60 * 24));
      
      if (diffDays === 0) return 'Today';
      if (diffDays === 1) return 'Tomorrow';
      if (diffDays > 1 && diffDays <= 7) return `In ${diffDays} days`;
      if (diffDays > 7 && diffDays <= 30) return `In ${Math.ceil(diffDays / 7)} weeks`;
      if (diffDays > 30) return `In ${Math.ceil(diffDays / 30)} months`;
      if (diffDays < 0 && diffDays >= -1) return 'Yesterday';
      if (diffDays < -1 && diffDays >= -7) return `${Math.abs(diffDays)} days ago`;
      return eventDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };
    
    const EventItem = ({ event, isAccessible }) => (
      <div 
        className={`event-list-item ${event.status} ${!isAccessible ? 'locked' : ''} ${selectedEvent?.id === event.id ? 'selected' : ''}`}
        onClick={() => handleSelectEvent(event)}
      >
        <div className="event-list-info">
          <div className="event-list-name">{event.name}</div>
          <div className="event-list-meta">
            <span>{event.venue}</span>
            <span className="event-list-date">{event.date}</span>
          </div>
        </div>
        <div className="event-list-right">
          {event.status === 'live' && (
            <div className="event-list-status live">
              <span className="pulse-small"></span> LIVE
            </div>
          )}
          {event.status === 'ended' && (
            <div className="event-list-status ended">Ended</div>
          )}
          {event.status === 'upcoming' && isAccessible && (
            <div className="event-list-status today">{getRelativeDate(event.dateTime)}</div>
          )}
          {!isAccessible && (
            <div className="event-list-locked">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <span>{getRelativeDate(event.dateTime)}</span>
            </div>
          )}
        </div>
        {selectedEvent?.id === event.id && (
          <div className="event-list-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
        )}
      </div>
    );
    
    return (
      <div className="modal-overlay" onClick={() => setShowEventsModal(false)}>
        <div className="modal-content events-modal" onClick={e => e.stopPropagation()}>
          <div className="modal-header">
            <h3>Select Event</h3>
            <button className="close-btn" onClick={() => setShowEventsModal(false)}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          
          <div className="events-list-container">
            {/* Live Now */}
            {liveEvents.length > 0 && (
              <div className="events-group">
                <div className="events-group-header live">
                  <span className="pulse-small"></span> Happening Now
                </div>
                {liveEvents.map(event => (
                  <EventItem key={event.id} event={event} isAccessible={true} />
                ))}
              </div>
            )}
            
            {/* Today (Later) */}
            {todayEvents.length > 0 && (
              <div className="events-group">
                <div className="events-group-header today">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{width: 16, height: 16}}>
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  Later Today
                </div>
                {todayEvents.map(event => (
                  <EventItem key={event.id} event={event} isAccessible={true} />
                ))}
              </div>
            )}
            
            {/* Past Events */}
            {pastEvents.length > 0 && (
              <div className="events-group">
                <div className="events-group-header past">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{width: 16, height: 16}}>
                    <path d="M3 3v5h5M3.05 13A9 9 0 1 0 6 5.3L3 8"/>
                  </svg>
                  Past Events (Reports Only)
                </div>
                {pastEvents.map(event => (
                  <EventItem key={event.id} event={event} isAccessible={true} />
                ))}
              </div>
            )}
            
            {/* Future Events */}
            {futureEvents.length > 0 && (
              <div className="events-group">
                <div className="events-group-header future">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{width: 16, height: 16}}>
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  Upcoming Events
                </div>
                <div className="future-events-notice">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                  </svg>
                  <span>Future events are view-only until their start date</span>
                </div>
                {futureEvents.map(event => (
                  <EventItem key={event.id} event={event} isAccessible={false} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    );
  };

  // Dashboard View
  const Dashboard = () => (
    <div className="dashboard">
      {/* Reports Only Banner for Past Events */}
      {isReportsOnlyMode && (
        <div className="reports-only-banner">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M3 3v5h5M3.05 13A9 9 0 1 0 6 5.3L3 8"/>
          </svg>
          <div className="reports-only-text">
            <span className="reports-only-title">Past Event - Reports Only</span>
            <span className="reports-only-desc">Scanning and selling are disabled for past events</span>
          </div>
        </div>
      )}
      
      <div className="stats-grid">
        <div className="stat-card primary">
          <div className="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div className="stat-content">
            <span className="stat-value">{selectedEvent?.checkedIn.toLocaleString()}</span>
            <span className="stat-label">{isReportsOnlyMode ? 'Total Check-ins' : 'Checked In'}</span>
          </div>
          {!isReportsOnlyMode && (
            <div className="stat-trend up">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 6l-9.5 9.5-5-5L1 18"/>
              </svg>
              +{liveStats.checkInsPerMinute}/min
            </div>
          )}
        </div>

        <div className="stat-card">
          <div className="stat-icon cyan">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="1" y="4" width="22" height="16" rx="2"/>
              <path d="M1 10h22"/>
            </svg>
          </div>
          <div className="stat-content">
            <span className="stat-value">{formatCurrency(selectedEvent?.revenue)}</span>
            <span className="stat-label">Total Revenue</span>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
          </div>
          <div className="stat-content">
            <span className="stat-value">{isReportsOnlyMode ? `${Math.round(selectedEvent?.checkedIn / selectedEvent?.capacity * 100)}%` : `${liveStats.currentCapacity}%`}</span>
            <span className="stat-label">{isReportsOnlyMode ? 'Final Capacity' : 'Venue Capacity'}</span>
          </div>
          <div className="capacity-bar">
            <div className="capacity-fill" style={{ width: isReportsOnlyMode ? `${Math.round(selectedEvent?.checkedIn / selectedEvent?.capacity * 100)}%` : `${liveStats.currentCapacity}%` }}></div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <div className="stat-content">
            <span className="stat-value">{selectedEvent?.sold.toLocaleString()}</span>
            <span className="stat-label">Tickets Sold</span>
          </div>
        </div>
      </div>

      {!isReportsOnlyMode && (
        <div className="quick-actions">
          <h3>Quick Actions</h3>
          <div className="actions-grid">
            <button className="action-btn scan" onClick={() => setActiveTab('checkin')}>
              <div className="action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                  <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>
            <span>Scan Ticket</span>
          </button>
          <button className="action-btn sell" onClick={() => setActiveTab('sales')}>
            <div className="action-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
            </div>
            <span>Sell Tickets</span>
          </button>
          <button className="action-btn guest" onClick={() => setActiveView('guestlist')}>
            <div className="action-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
              </svg>
            </div>
            <span>Guest List</span>
          </button>
          <button className="action-btn staff" onClick={() => setActiveView('staff')}>
            <div className="action-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <span>Staff</span>
          </button>
        </div>
      </div>
      )}

      <div className="recent-activity">
        <div className="section-header">
          <h3>{isReportsOnlyMode ? 'Event Summary' : 'Recent Check-ins'}</h3>
          {!isReportsOnlyMode && <button className="see-all" onClick={() => setActiveTab('checkin')}>See all</button>}
        </div>
        {isReportsOnlyMode ? (
          <div className="event-summary-stats">
            <div className="summary-stat-item">
              <span className="summary-stat-label">Total Duration</span>
              <span className="summary-stat-value">6h 30m</span>
            </div>
            <div className="summary-stat-item">
              <span className="summary-stat-label">Peak Attendance</span>
              <span className="summary-stat-value">{selectedEvent?.checkedIn.toLocaleString()}</span>
            </div>
            <div className="summary-stat-item">
              <span className="summary-stat-label">Avg Check-in Rate</span>
              <span className="summary-stat-value">52/min</span>
            </div>
            <div className="summary-stat-item">
              <span className="summary-stat-label">No-shows</span>
              <span className="summary-stat-value">{(selectedEvent?.sold - selectedEvent?.checkedIn).toLocaleString()}</span>
            </div>
          </div>
        ) : (
        <div className="activity-list">
          {recentScans.slice(0, 4).map(scan => (
            <div key={scan.id} className={`activity-item ${scan.status}`}>
              <div className="activity-avatar">
                {scan.name.split(' ').map(n => n[0]).join('')}
              </div>
              <div className="activity-info">
                <span className="activity-name">{scan.name}</span>
                <span className="activity-meta">{scan.ticket} • {scan.zone}</span>
              </div>
              <div className="activity-time">{scan.time}</div>
              <div className={`activity-status ${scan.status}`}>
                {scan.status === 'valid' && (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                )}
                {scan.status === 'duplicate' && (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                  </svg>
                )}
              </div>
            </div>
          ))}
        </div>
        )}
      </div>
    </div>
  );

  // Check-in View
  const CheckIn = () => (
    <div className="checkin-view">
      {isReportsOnlyMode ? (
        <div className="reports-only-view">
          <div className="reports-only-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
              <path d="M3 3v5h5M3.05 13A9 9 0 1 0 6 5.3L3 8"/>
            </svg>
          </div>
          <h3>Past Event</h3>
          <p>Check-in is not available for past events. You can view the reports for this event.</p>
          <button className="view-reports-btn" onClick={() => setActiveTab('reports')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 20V10M12 20V4M6 20v-6"/>
            </svg>
            View Reports
          </button>
        </div>
      ) : (
      <>
      <div className="scanner-container">
        <div className={`scanner-frame ${isScanning ? 'scanning' : ''} ${scanResult ? scanResult.status : ''}`}>
          <div className="scanner-corners">
            <span></span><span></span><span></span><span></span>
          </div>
          {isScanning && (
            <div className="scanner-line"></div>
          )}
          {!isScanning && !scanResult && (
            <div className="scanner-prompt">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <path d="M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7zM14 14h3v3h-3z"/>
              </svg>
              <span>Point camera at QR code</span>
            </div>
          )}
          {scanResult && (
            <div className={`scan-result ${scanResult.status}`}>
              {scanResult.status === 'valid' && (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="result-icon">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12l3 3 5-6"/>
                </svg>
              )}
              {scanResult.status === 'duplicate' && (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="result-icon">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M12 8v4M12 16h.01"/>
                </svg>
              )}
              {scanResult.status === 'invalid' && (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="result-icon">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M15 9l-6 6M9 9l6 6"/>
                </svg>
              )}
            </div>
          )}
        </div>

        {scanResult && (
          <div className={`result-card ${scanResult.status}`}>
            <div className="result-header">
              <span className="result-status">
                {scanResult.status === 'valid' && 'ACCESS GRANTED'}
                {scanResult.status === 'duplicate' && 'ALREADY SCANNED'}
                {scanResult.status === 'invalid' && 'INVALID TICKET'}
              </span>
            </div>
            {scanResult.name && (
              <div className="result-details">
                <div className="result-name">{scanResult.name}</div>
                <div className="result-ticket">{scanResult.ticket}</div>
                {scanResult.seat && <div className="result-seat">{scanResult.seat}</div>}
              </div>
            )}
            <div className="result-message">{scanResult.message}</div>
          </div>
        )}

        <button className="scan-btn" onClick={handleScan} disabled={isScanning}>
          {isScanning ? (
            <>
              <div className="btn-spinner"></div>
              Scanning...
            </>
          ) : scanResult ? (
            <>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
              Scan Next
            </>
          ) : (
            <>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
              Start Scanning
            </>
          )}
        </button>

        <button className="manual-entry-btn" onClick={() => setActiveView('manual')}>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Manual Entry
        </button>
      </div>

      <div className="checkin-stats">
        <div className="stat-pill">
          <span className="pill-value">{liveStats.checkInsPerMinute}</span>
          <span className="pill-label">/min</span>
        </div>
        <div className="stat-pill">
          <span className="pill-value">{liveStats.avgWaitTime}</span>
          <span className="pill-label">avg wait</span>
        </div>
        <div className="stat-pill">
          <span className="pill-value">{selectedEvent?.checkedIn.toLocaleString()}</span>
          <span className="pill-label">total</span>
        </div>
      </div>

      <div className="recent-scans">
        <h4>Recent Scans</h4>
        <div className="scans-list">
          {recentScans.map(scan => (
            <div key={scan.id} className={`scan-item ${scan.status}`}>
              <div className={`scan-status-icon ${scan.status}`}>
                {scan.status === 'valid' && '✓'}
                {scan.status === 'duplicate' && '!'}
                {scan.status === 'invalid' && '✕'}
              </div>
              <div className="scan-info">
                <span className="scan-name">{scan.name}</span>
                <span className="scan-ticket">{scan.ticket}</span>
              </div>
              <span className="scan-time">{scan.time}</span>
            </div>
          ))}
        </div>
      </div>
      </>
      )}
    </div>
  );

  // Sales / POS View
  const Sales = () => (
    <div className="sales-view">
      {isReportsOnlyMode ? (
        <div className="reports-only-view">
          <div className="reports-only-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
              <circle cx="9" cy="21" r="1"/>
              <circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
          </div>
          <h3>Past Event</h3>
          <p>Ticket sales are not available for past events. You can view the sales reports for this event.</p>
          <button className="view-reports-btn" onClick={() => setActiveTab('reports')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 20V10M12 20V4M6 20v-6"/>
            </svg>
            View Reports
          </button>
        </div>
      ) : activeView === 'cart' ? (
        <div className="cart-view">
          <div className="cart-header">
            <button className="back-btn" onClick={() => setActiveView(null)}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
            </button>
            <h3>Cart</h3>
            <span className="cart-count">{cartCount} items</span>
          </div>

          <div className="cart-items">
            {cartItems.map(item => (
              <div key={item.id} className="cart-item">
                <div className="item-color" style={{ background: item.color }}></div>
                <div className="item-details">
                  <span className="item-name">{item.name}</span>
                  <span className="item-price">{formatCurrency(item.price)}</span>
                </div>
                <div className="item-quantity">
                  <button onClick={() => updateQuantity(item.id, -1)}>−</button>
                  <span>{item.quantity}</span>
                  <button onClick={() => updateQuantity(item.id, 1)}>+</button>
                </div>
                <span className="item-total">{formatCurrency(item.price * item.quantity)}</span>
              </div>
            ))}
          </div>

          <div className="cart-summary">
            <div className="summary-row">
              <span>Subtotal</span>
              <span>{formatCurrency(cartTotal)}</span>
            </div>
            <div className="summary-row">
              <span>TVA (19%)</span>
              <span>{formatCurrency(cartTotal * 0.19)}</span>
            </div>
            <div className="summary-row total">
              <span>Total</span>
              <span>{formatCurrency(cartTotal)}</span>
            </div>
          </div>

          <div className="payment-methods">
            <h4>Payment Method</h4>
            <div className="methods-grid">
              <button 
                className={`method-btn tap-to-pay ${paymentMethod === 'card' ? 'active processing' : ''}`}
                onClick={() => processPayment('card')}
                disabled={paymentMethod}
              >
                <div className="tap-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                  <div className="contactless-waves">
                    <span></span><span></span><span></span>
                  </div>
                </div>
                <span>Tap to Pay</span>
                <span className="powered-by">Powered by Stripe</span>
                {paymentMethod === 'card' && <div className="method-spinner"></div>}
              </button>
              <button 
                className={`method-btn ${paymentMethod === 'cash' ? 'active processing' : ''}`}
                onClick={() => processPayment('cash')}
                disabled={paymentMethod}
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <rect x="2" y="6" width="20" height="12" rx="2"/>
                  <circle cx="12" cy="12" r="2"/>
                  <path d="M6 12h.01M18 12h.01"/>
                </svg>
                <span>Cash</span>
                {paymentMethod === 'cash' && <div className="method-spinner"></div>}
              </button>
            </div>
          </div>

          {showPaymentSuccess && !showEmailCapture && (
            <div className="payment-success">
              <div className="success-content">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12l3 3 5-6"/>
                </svg>
                <span>Payment Successful!</span>
                <span className="success-amount">{formatCurrency(cartTotal)}</span>
                <div className="success-actions">
                  <button className="email-btn" onClick={() => setShowEmailCapture(true)}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                      <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Send Tickets to Email
                  </button>
                  <button className="skip-btn" onClick={() => {
                    setShowPaymentSuccess(false);
                    setActiveView(null);
                    setCartItems([]);
                    setPaymentMethod(null);
                  }}>
                    Skip & Finish
                  </button>
                </div>
              </div>
            </div>
          )}

          {showEmailCapture && (
            <div className="email-capture-modal">
              <div className="email-capture-content">
                <div className="email-capture-header">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                  <h4>Send Tickets via Email</h4>
                </div>
                <p className="email-capture-desc">Enter customer's email to send their tickets</p>
                <div className="email-input-group">
                  <input 
                    type="email" 
                    placeholder="customer@email.com" 
                    value={buyerEmail}
                    onChange={(e) => setBuyerEmail(e.target.value)}
                    autoFocus
                  />
                </div>
                <div className="email-capture-actions">
                  <button 
                    className="send-email-btn"
                    onClick={() => {
                      setSendingEmail(true);
                      // API call would go here
                      setTimeout(() => {
                        setSendingEmail(false);
                        setShowEmailCapture(false);
                        setShowPaymentSuccess(false);
                        setActiveView(null);
                        setCartItems([]);
                        setPaymentMethod(null);
                        setBuyerEmail('');
                      }, 1500);
                    }}
                    disabled={!buyerEmail || sendingEmail}
                  >
                    {sendingEmail ? (
                      <>
                        <div className="btn-spinner"></div>
                        Sending...
                      </>
                    ) : (
                      <>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <line x1="22" y1="2" x2="11" y2="13"/>
                          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Send Tickets
                      </>
                    )}
                  </button>
                  <button 
                    className="cancel-email-btn"
                    onClick={() => {
                      setShowEmailCapture(false);
                      setShowPaymentSuccess(false);
                      setActiveView(null);
                      setCartItems([]);
                      setPaymentMethod(null);
                      setBuyerEmail('');
                    }}
                  >
                    Skip
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      ) : (
        <>
          <div className="ticket-selection">
            <h3>Select Tickets</h3>
            <div className="tickets-grid">
              {ticketTypes.map(ticket => (
                <div 
                  key={ticket.id} 
                  className={`ticket-card ${ticket.available === 0 ? 'soldout' : ''}`}
                  onClick={() => ticket.available > 0 && addToCart(ticket)}
                >
                  <div className="ticket-badge" style={{ background: ticket.color }}></div>
                  <div className="ticket-info">
                    <span className="ticket-name">{ticket.name}</span>
                    <span className="ticket-price">{formatCurrency(ticket.price)}</span>
                  </div>
                  <div className="ticket-availability">
                    {ticket.available > 0 ? (
                      <span className="available">{ticket.available} left</span>
                    ) : (
                      <span className="soldout">Sold Out</span>
                    )}
                  </div>
                  {ticket.available > 0 && (
                    <button className="add-btn">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                      </svg>
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>

          <div className="sales-history">
            <div className="section-header">
              <h3>Today's Sales</h3>
              <span className="sales-total">{formatCurrency(salesHistory.reduce((sum, s) => sum + s.total, 0))}</span>
            </div>
            <div className="history-list">
              {salesHistory.map(sale => (
                <div key={sale.id} className="history-item">
                  <div className="sale-icon">
                    {sale.method === 'card' ? (
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <rect x="1" y="4" width="22" height="16" rx="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                      </svg>
                    ) : (
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2"/>
                      </svg>
                    )}
                  </div>
                  <div className="sale-info">
                    <span className="sale-desc">{sale.tickets}x {sale.type}</span>
                    <span className="sale-time">{sale.time}</span>
                  </div>
                  <span className="sale-amount">{formatCurrency(sale.total)}</span>
                </div>
              ))}
            </div>
          </div>

          {cartCount > 0 && (
            <div className="cart-fab" onClick={() => setActiveView('cart')}>
              <div className="fab-badge">{cartCount}</div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
              <span>{formatCurrency(cartTotal)}</span>
            </div>
          )}
        </>
      )}
    </div>
  );

  // Reports View
  const Reports = () => (
    <div className="reports-view">
      <div className="reports-header">
        <h3>Live Reports</h3>
        <div className="report-time">
          <span className="pulse-dot"></span>
          Live • Updated just now
        </div>
      </div>

      <div className="metrics-grid">
        <div className="metric-card large">
          <div className="metric-header">
            <span className="metric-label">Check-in Rate</span>
            <span className="metric-trend up">+12%</span>
          </div>
          <div className="metric-value">{liveStats.checkInsPerMinute}/min</div>
          <div className="mini-chart">
            <svg viewBox="0 0 200 50" className="chart-line">
              <polyline
                points="0,40 20,35 40,38 60,25 80,30 100,20 120,22 140,15 160,18 180,10 200,8"
                fill="none"
                stroke="url(#chartGrad)"
                strokeWidth="2"
              />
              <defs>
                <linearGradient id="chartGrad" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stopColor="#8B5CF6" stopOpacity="0.3"/>
                  <stop offset="100%" stopColor="#8B5CF6"/>
                </linearGradient>
              </defs>
            </svg>
          </div>
        </div>

        <div className="metric-card">
          <span className="metric-label">Sales Rate</span>
          <div className="metric-value">{liveStats.salesPerMinute}/min</div>
        </div>

        <div className="metric-card">
          <span className="metric-label">Peak Hour</span>
          <div className="metric-value">{liveStats.peakHour}</div>
        </div>
      </div>

      <div className="report-section">
        <h4>Gate Performance</h4>
        <div className="gates-list">
          {['Gate A', 'Gate B', 'Gate C', 'VIP Entrance'].map((gate, i) => {
            const progress = [68, 82, 45, 23][i];
            const scans = [8934, 12456, 5678, 2345][i];
            return (
              <div key={gate} className="gate-item">
                <div className="gate-info">
                  <span className="gate-name">{gate}</span>
                  <span className="gate-scans">{scans.toLocaleString()} scans</span>
                </div>
                <div className="gate-bar">
                  <div className="gate-progress" style={{ width: `${progress}%` }}></div>
                </div>
                <span className="gate-percent">{progress}%</span>
              </div>
            );
          })}
        </div>
      </div>

      <div className="report-section">
        <h4>Revenue Breakdown</h4>
        <div className="revenue-chart">
          {ticketTypes.filter(t => t.available < 234).map((type, i) => {
            const revenues = [2456700, 1823400, 890000, 234500];
            const percentages = [45, 34, 16, 5];
            return (
              <div key={type.id} className="revenue-bar">
                <div className="revenue-label">
                  <div className="revenue-dot" style={{ background: type.color }}></div>
                  <span>{type.name}</span>
                </div>
                <div className="revenue-track">
                  <div 
                    className="revenue-fill" 
                    style={{ width: `${percentages[i]}%`, background: type.color }}
                  ></div>
                </div>
                <span className="revenue-amount">{formatCurrency(revenues[i])}</span>
              </div>
            );
          })}
        </div>
      </div>

      <div className="report-section">
        <h4>Hourly Distribution</h4>
        <div className="hourly-chart">
          {['16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'].map((hour, i) => {
            const heights = [20, 45, 78, 95, 85, 60, 35];
            return (
              <div key={hour} className="hour-bar">
                <div className="bar-fill" style={{ height: `${heights[i]}%` }}></div>
                <span className="hour-label">{hour}</span>
              </div>
            );
          })}
        </div>
      </div>

      <button className="export-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export Report
      </button>
    </div>
  );

  // Settings View
  const Settings = () => (
    <div className="settings-view">
      <div className="settings-header">
        <h3>Settings</h3>
      </div>

      <div className="settings-section">
        <h4>Account</h4>
        <div className="setting-item">
          <div className="setting-info">
            <span className="setting-label">Staff Member</span>
            <span className="setting-value">Alexandru Marin</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="setting-arrow">
            <path d="M9 18l6-6-6-6"/>
          </svg>
        </div>
        <div className="setting-item">
          <div className="setting-info">
            <span className="setting-label">Role</span>
            <span className="setting-value">Gate Staff + POS</span>
          </div>
        </div>
        <div className="setting-item">
          <div className="setting-info">
            <span className="setting-label">Assigned Gate</span>
            <span className="setting-value">Gate A</span>
          </div>
        </div>
      </div>

      <div className="settings-section">
        <h4>Scanner</h4>
        <div className="setting-item toggle">
          <div className="setting-info">
            <span className="setting-label">Vibration Feedback</span>
            <span className="setting-desc">Vibrate on successful scan</span>
          </div>
          <div 
            className={`toggle-switch ${vibrationFeedback ? 'active' : ''}`}
            onClick={() => setVibrationFeedback(!vibrationFeedback)}
          >
            <div className="toggle-thumb"></div>
          </div>
        </div>
        <div className="setting-item toggle">
          <div className="setting-info">
            <span className="setting-label">Sound Effects</span>
            <span className="setting-desc">Play sound on scan</span>
          </div>
          <div 
            className={`toggle-switch ${soundEffects ? 'active' : ''}`}
            onClick={() => setSoundEffects(!soundEffects)}
          >
            <div className="toggle-thumb"></div>
          </div>
        </div>
        <div className="setting-item toggle">
          <div className="setting-info">
            <span className="setting-label">Auto-confirm Valid</span>
            <span className="setting-desc">Skip confirmation for valid tickets</span>
          </div>
          <div 
            className={`toggle-switch ${autoConfirmValid ? 'active' : ''}`}
            onClick={() => setAutoConfirmValid(!autoConfirmValid)}
          >
            <div className="toggle-thumb"></div>
          </div>
        </div>
      </div>

      <div className="settings-section">
        <h4>Offline Mode</h4>
        <div className="setting-item toggle">
          <div className="setting-info">
            <span className="setting-label">Enable Offline Mode</span>
            <span className="setting-desc">Continue scanning without internet</span>
          </div>
          <div 
            className={`toggle-switch ${offlineMode ? 'active' : ''}`}
            onClick={() => setOfflineMode(!offlineMode)}
          >
            <div className="toggle-thumb"></div>
          </div>
        </div>
        <div className="offline-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 16v-4M12 8h.01"/>
          </svg>
          <span>{cachedTickets.toLocaleString()} tickets cached for offline scanning</span>
        </div>
      </div>

      <div className="settings-section">
        <h4>Hardware</h4>
        <div className="setting-item">
          <div className="setting-info">
            <span className="setting-label">Card Reader</span>
            <span className="setting-value connected">SumUp Air • Connected</span>
          </div>
        </div>
        <div className="setting-item">
          <div className="setting-info">
            <span className="setting-label">Receipt Printer</span>
            <span className="setting-value">Star TSP143</span>
          </div>
        </div>
      </div>

      {/* Admin Only Sections */}
      {currentUserRole === 'admin' && (
        <>
          <div className="settings-section admin-section">
            <div className="admin-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
              Admin Controls
            </div>
            
            <div className="setting-item clickable" onClick={() => setShowGateManager(true)}>
              <div className="setting-info">
                <span className="setting-label">Gate Management</span>
                <span className="setting-desc">Add, edit, or remove gates and checkpoints</span>
              </div>
              <div className="setting-badge">{gates.length} gates</div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="setting-arrow">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
            
            <div className="setting-item clickable" onClick={() => setShowStaffAssignment(true)}>
              <div className="setting-info">
                <span className="setting-label">Staff Assignment</span>
                <span className="setting-desc">Assign staff to gates and manage schedules</span>
              </div>
              <div className="setting-badge">{staffSchedules.length} assigned</div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="setting-arrow">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </div>
          </div>
        </>
      )}

      <button className="logout-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        End Shift & Logout
      </button>
    </div>
  );

  // Staff View Modal
  // Staff Modal - Shows all scanner users with details
  const StaffModal = () => {
    const formatCurrency = (amount) => new Intl.NumberFormat('ro-RO', { style: 'decimal', minimumFractionDigits: 0 }).format(amount) + ' lei';
    
    return (
    <div className="modal-overlay" onClick={() => setActiveView(null)}>
      <div className="modal-content large" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Staff Overview</h3>
          <button className="close-btn" onClick={() => setActiveView(null)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        
        <div className="staff-summary">
          <div className="staff-summary-item">
            <span className="staff-summary-value">{staffMembers.filter(m => m.status === 'active').length}</span>
            <span className="staff-summary-label">Active</span>
          </div>
          <div className="staff-summary-item">
            <span className="staff-summary-value">{staffMembers.reduce((sum, m) => sum + m.scans, 0)}</span>
            <span className="staff-summary-label">Total Scans</span>
          </div>
          <div className="staff-summary-item">
            <span className="staff-summary-value">{staffMembers.reduce((sum, m) => sum + m.sales, 0)}</span>
            <span className="staff-summary-label">Total Sales</span>
          </div>
        </div>
        
        <div className="staff-list detailed">
          {staffMembers.map(member => (
            <div key={member.id} className={`staff-item-detailed ${member.status}`}>
              <div className="staff-item-header">
                <div className="staff-avatar">
                  {member.name.split(' ').map(n => n[0]).join('')}
                </div>
                <div className="staff-info">
                  <span className="staff-name">{member.name}</span>
                  <span className="staff-role">{member.role}</span>
                </div>
                <div className={`staff-status ${member.status}`}>
                  {member.status === 'active' ? '● Active' : '○ Break'}
                </div>
              </div>
              
              <div className="staff-details-grid">
                <div className="staff-detail">
                  <span className="detail-label">Gate</span>
                  <span className="detail-value">{member.gate}</span>
                </div>
                <div className="staff-detail">
                  <span className="detail-label">Shift Start</span>
                  <span className="detail-value">{member.shiftStart}</span>
                </div>
                <div className="staff-detail">
                  <span className="detail-label">Last Active</span>
                  <span className="detail-value">{member.lastActive}</span>
                </div>
                {member.scans > 0 && (
                  <div className="staff-detail">
                    <span className="detail-label">Scans</span>
                    <span className="detail-value highlight">{member.scans}</span>
                  </div>
                )}
                {member.sales > 0 && (
                  <div className="staff-detail">
                    <span className="detail-label">Sales</span>
                    <span className="detail-value highlight">{member.sales}</span>
                  </div>
                )}
                {(member.cashCollected > 0 || member.cardCollected > 0) && (
                  <>
                    <div className="staff-detail">
                      <span className="detail-label">Cash</span>
                      <span className="detail-value cash">{formatCurrency(member.cashCollected)}</span>
                    </div>
                    <div className="staff-detail">
                      <span className="detail-label">Card</span>
                      <span className="detail-value card">{formatCurrency(member.cardCollected)}</span>
                    </div>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )};

  // Guest List Modal
  const GuestListModal = () => (
    <div className="modal-overlay" onClick={() => setActiveView(null)}>
      <div className="modal-content large" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Guest List</h3>
          <button className="close-btn" onClick={() => setActiveView(null)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div className="search-bar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
          </svg>
          <input type="text" placeholder="Search guests..." />
        </div>
        <div className="guest-list">
          {[
            { name: 'VIP Guest 1', type: 'Artist Guest', checked: true },
            { name: 'VIP Guest 2', type: 'Sponsor', checked: true },
            { name: 'Press Member 1', type: 'Press', checked: false },
            { name: 'Influencer 1', type: 'Influencer', checked: false },
            { name: 'VIP Guest 3', type: 'VIP', checked: false },
          ].map((guest, i) => (
            <div key={i} className={`guest-item ${guest.checked ? 'checked' : ''}`}>
              <div className="guest-avatar">
                {guest.name.split(' ').map(n => n[0]).join('')}
              </div>
              <div className="guest-info">
                <span className="guest-name">{guest.name}</span>
                <span className="guest-type">{guest.type}</span>
              </div>
              <button className={`check-btn ${guest.checked ? 'checked' : ''}`}>
                {guest.checked ? (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                ) : (
                  'Check In'
                )}
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );

  // Notifications Panel
  const NotificationsPanel = () => (
    <div className="notifications-panel" onClick={() => setShowNotifications(false)}>
      <div className="notifications-content" onClick={e => e.stopPropagation()}>
        <div className="notifications-header">
          <h3>Notifications</h3>
          <button className="mark-read">Mark all read</button>
        </div>
        <div className="notifications-list">
          {notifications.map(notif => (
            <div key={notif.id} className={`notification-item ${notif.type} ${notif.unread ? 'unread' : ''}`}>
              <div className="notif-icon">
                {notif.type === 'alert' && (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                  </svg>
                )}
                {notif.type === 'info' && (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4M12 8h.01"/>
                  </svg>
                )}
                {notif.type === 'success' && (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                  </svg>
                )}
              </div>
              <div className="notif-content">
                <span className="notif-message">{notif.message}</span>
                <span className="notif-time">{notif.time}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );

  // Manual Entry Modal
  const ManualEntryModal = () => (
    <div className="modal-overlay" onClick={() => setActiveView(null)}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Manual Entry</h3>
          <button className="close-btn" onClick={() => setActiveView(null)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div className="manual-form">
          <div className="form-group">
            <label>Ticket Code</label>
            <input type="text" placeholder="Enter ticket code or order ID" />
          </div>
          <div className="form-group">
            <label>Or scan ID</label>
            <input type="text" placeholder="Guest name or ID number" />
          </div>
          <button className="submit-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Validate & Check In
          </button>
        </div>
      </div>
    </div>
  );

  // Gate Manager Modal (Admin Only)
  const GateManagerModal = () => {
    const [editingGate, setEditingGate] = useState(null);
    const [newGateName, setNewGateName] = useState('');
    const [newGateType, setNewGateType] = useState('entry');
    const [newGateLocation, setNewGateLocation] = useState('');
    
    const addGate = () => {
      if (!newGateName.trim()) return;
      const newGate = {
        id: Date.now(),
        name: newGateName,
        type: newGateType,
        location: newGateLocation,
        active: true
      };
      setGates([...gates, newGate]);
      setNewGateName('');
      setNewGateType('entry');
      setNewGateLocation('');
    };
    
    const removeGate = (gateId) => {
      setGates(gates.filter(g => g.id !== gateId));
    };
    
    const toggleGate = (gateId) => {
      setGates(gates.map(g => g.id === gateId ? {...g, active: !g.active} : g));
    };
    
    return (
      <div className="modal-overlay" onClick={() => setShowGateManager(false)}>
        <div className="modal-content large" onClick={e => e.stopPropagation()}>
          <div className="modal-header">
            <h3>Gate Management</h3>
            <button className="close-btn" onClick={() => setShowGateManager(false)}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          
          {/* Add New Gate Form */}
          <div className="gate-form">
            <h4>Add New Gate</h4>
            <div className="gate-form-row">
              <input 
                type="text" 
                placeholder="Gate name (e.g., Gate C)"
                value={newGateName}
                onChange={(e) => setNewGateName(e.target.value)}
              />
              <select value={newGateType} onChange={(e) => setNewGateType(e.target.value)}>
                <option value="entry">Entry Gate</option>
                <option value="vip">VIP Gate</option>
                <option value="pos">POS / Box Office</option>
                <option value="exit">Exit Gate</option>
              </select>
            </div>
            <div className="gate-form-row">
              <input 
                type="text" 
                placeholder="Location description"
                value={newGateLocation}
                onChange={(e) => setNewGateLocation(e.target.value)}
              />
              <button className="add-gate-btn" onClick={addGate}>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="12" y1="5" x2="12" y2="19"/>
                  <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Gate
              </button>
            </div>
          </div>
          
          {/* Gates List */}
          <div className="gates-list">
            <h4>Current Gates ({gates.length})</h4>
            {gates.map(gate => (
              <div key={gate.id} className={`gate-item ${!gate.active ? 'inactive' : ''}`}>
                <div className="gate-icon">
                  {gate.type === 'entry' && (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                    </svg>
                  )}
                  {gate.type === 'vip' && (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  )}
                  {gate.type === 'pos' && (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                      <line x1="8" y1="21" x2="16" y2="21"/>
                      <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                  )}
                  {gate.type === 'exit' && (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                  )}
                </div>
                <div className="gate-info">
                  <span className="gate-name">{gate.name}</span>
                  <span className="gate-location">{gate.location}</span>
                </div>
                <span className={`gate-type-badge ${gate.type}`}>{gate.type}</span>
                <div className="gate-actions">
                  <button 
                    className={`toggle-gate-btn ${gate.active ? 'active' : ''}`}
                    onClick={() => toggleGate(gate.id)}
                  >
                    {gate.active ? 'Active' : 'Inactive'}
                  </button>
                  <button className="remove-gate-btn" onClick={() => removeGate(gate.id)}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="3 6 5 6 21 6"/>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  };

  // Staff Assignment Modal (Admin Only)
  const StaffAssignmentModal = () => {
    const [editingSchedule, setEditingSchedule] = useState(null);
    const [newAssignment, setNewAssignment] = useState({
      staffName: '',
      gateId: '',
      shiftStart: '18:00',
      shiftEnd: '02:00',
      role: 'scanner'
    });
    
    const addAssignment = () => {
      if (!newAssignment.staffName || !newAssignment.gateId) return;
      const gate = gates.find(g => g.id === parseInt(newAssignment.gateId));
      const assignment = {
        id: Date.now(),
        staffId: Date.now(),
        staffName: newAssignment.staffName,
        gateId: parseInt(newAssignment.gateId),
        gateName: gate?.name || '',
        shiftStart: newAssignment.shiftStart,
        shiftEnd: newAssignment.shiftEnd,
        role: newAssignment.role
      };
      setStaffSchedules([...staffSchedules, assignment]);
      setNewAssignment({ staffName: '', gateId: '', shiftStart: '18:00', shiftEnd: '02:00', role: 'scanner' });
    };
    
    const removeAssignment = (scheduleId) => {
      setStaffSchedules(staffSchedules.filter(s => s.id !== scheduleId));
    };
    
    return (
      <div className="modal-overlay" onClick={() => setShowStaffAssignment(false)}>
        <div className="modal-content large" onClick={e => e.stopPropagation()}>
          <div className="modal-header">
            <h3>Staff Assignment & Scheduling</h3>
            <button className="close-btn" onClick={() => setShowStaffAssignment(false)}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          
          {/* Add New Assignment Form */}
          <div className="assignment-form">
            <h4>Add Staff Assignment</h4>
            <div className="assignment-form-grid">
              <div className="form-group">
                <label>Staff Name</label>
                <input 
                  type="text" 
                  placeholder="Full name"
                  value={newAssignment.staffName}
                  onChange={(e) => setNewAssignment({...newAssignment, staffName: e.target.value})}
                />
              </div>
              <div className="form-group">
                <label>Assign to Gate</label>
                <select 
                  value={newAssignment.gateId}
                  onChange={(e) => setNewAssignment({...newAssignment, gateId: e.target.value})}
                >
                  <option value="">Select gate...</option>
                  {gates.filter(g => g.active).map(gate => (
                    <option key={gate.id} value={gate.id}>{gate.name}</option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label>Role</label>
                <select 
                  value={newAssignment.role}
                  onChange={(e) => setNewAssignment({...newAssignment, role: e.target.value})}
                >
                  <option value="scanner">Scanner</option>
                  <option value="pos">POS Sales</option>
                  <option value="supervisor">Supervisor</option>
                </select>
              </div>
              <div className="form-group">
                <label>Shift Start</label>
                <input 
                  type="time" 
                  value={newAssignment.shiftStart}
                  onChange={(e) => setNewAssignment({...newAssignment, shiftStart: e.target.value})}
                />
              </div>
              <div className="form-group">
                <label>Shift End</label>
                <input 
                  type="time" 
                  value={newAssignment.shiftEnd}
                  onChange={(e) => setNewAssignment({...newAssignment, shiftEnd: e.target.value})}
                />
              </div>
              <button className="add-assignment-btn" onClick={addAssignment}>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="12" y1="5" x2="12" y2="19"/>
                  <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Assignment
              </button>
            </div>
          </div>
          
          {/* Current Assignments */}
          <div className="assignments-list">
            <h4>Current Assignments ({staffSchedules.length})</h4>
            {staffSchedules.map(schedule => (
              <div key={schedule.id} className="assignment-item">
                <div className="assignment-avatar">
                  {schedule.staffName.split(' ').map(n => n[0]).join('')}
                </div>
                <div className="assignment-info">
                  <span className="assignment-name">{schedule.staffName}</span>
                  <span className="assignment-gate">{schedule.gateName}</span>
                </div>
                <div className="assignment-schedule">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  <span>{schedule.shiftStart} - {schedule.shiftEnd}</span>
                </div>
                <span className={`role-badge ${schedule.role}`}>{schedule.role}</span>
                <button className="remove-assignment-btn" onClick={() => removeAssignment(schedule.id)}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="app-container">
      <style>{cssStyles}</style>

      <Header />
      <EventSelector />

      <div className="main-content">
        {activeTab === 'dashboard' && <Dashboard />}
        {activeTab === 'checkin' && <CheckIn />}
        {activeTab === 'sales' && <Sales />}
        {activeTab === 'reports' && <Reports />}
        {activeTab === 'settings' && <Settings />}
      </div>

      <nav className="bottom-nav">
        <button 
          className={`nav-item ${activeTab === 'dashboard' ? 'active' : ''}`}
          onClick={() => { setActiveTab('dashboard'); setActiveView(null); }}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
          </svg>
          <span>Dashboard</span>
        </button>
        <button 
          className={`nav-item ${activeTab === 'checkin' ? 'active' : ''}`}
          onClick={() => { setActiveTab('checkin'); setActiveView(null); }}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
          <span>Check-in</span>
        </button>
        <button 
          className={`nav-item ${activeTab === 'sales' ? 'active' : ''}`}
          onClick={() => { setActiveTab('sales'); setActiveView(null); }}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="9" cy="21" r="1"/>
            <circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
          </svg>
          <span>Sales</span>
        </button>
        <button 
          className={`nav-item ${activeTab === 'reports' ? 'active' : ''}`}
          onClick={() => { setActiveTab('reports'); setActiveView(null); }}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M18 20V10M12 20V4M6 20v-6"/>
          </svg>
          <span>Reports</span>
        </button>
        <button 
          className={`nav-item ${activeTab === 'settings' ? 'active' : ''}`}
          onClick={() => { setActiveTab('settings'); setActiveView(null); }}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
          <span>Settings</span>
        </button>
      </nav>

      {showNotifications && <NotificationsPanel />}
      {showEventsModal && <EventsModal />}
      {activeView === 'staff' && <StaffModal />}
      {activeView === 'guestlist' && <GuestListModal />}
      {activeView === 'manual' && <ManualEntryModal />}
      {showGateManager && <GateManagerModal />}
      {showStaffAssignment && <StaffAssignmentModal />}
    </div>
  );
}
