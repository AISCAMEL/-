const CONFIG = {
  LINE: {
    CHANNEL_TOKEN: 'YOUR_LINE_CHANNEL_TOKEN',
    CHANNEL_SECRET: 'YOUR_LINE_CHANNEL_SECRET'
  },
  LINE_WORKS: {
    BOT_ID:          '',
    CLIENT_ID:       '',
    CLIENT_SECRET:   '',
    SERVICE_ACCOUNT: '',
    PRIVATE_KEY:     '',
    HQ_USER_ID:      ''
  },
  OPENROUTER: {
  API_KEY: 'YOUR_OPENROUTER_API_KEY',
  MODEL_FREE: 'deepseek/deepseek-chat-v3-0324',
  MODEL_PAID: 'anthropic/claude-haiku-4-5'
},
  SPREADSHEET: {
    ID: '17DwD3Nr5ItDxdzvP_DQ3ZceYaz3gg5X_icLTZsaToCE',
    PROLINE_FORM_ID: '1n9WXqI1HCZdNkPnFq_Q5K_fMXoglqZo0Hv3D1O85LFc',
    PROLINE_FORM_SHEET: 'form_3：かんたん審査',
    SHEETS: {
      LOAN:          'ローン案件管理',
      BUY:           '買取案件管理',
      LEASE:         'リース案件管理',
      CUSTOMER:      '顧客マスタ',
      FRANCHISEE:    '加盟店マスタ',
      CREDITOR:      '信販会社マスタ',
      REPAYMENT:     '返済管理',
      AFTER_SUPPORT: 'アフターサポート管理',
      CHAT_LOG:      '会話ログ',
      REPORT:        '週次レポート'
    }
  },
  SCORING: {
    RANK_A: 85,
    RANK_B: 60,
    RANK_C: 40,
    ASSUMED_RATE: 4.9,
    MIN_TENURE_MONTHS: 3,

    AGE_SENIOR_60: -5,
    AGE_SENIOR_65: -10,
    AGE_SENIOR_70: -15,
    BONUS_DOWNPAYMENT:  10,
    BONUS_SAVINGS_HIGH: 10,
    BONUS_SAVINGS_MID:   5,
    BONUS_OWN_HOME:      8,
    BONUS_FAMILY_HOME:   4,
    BONUS_GUARANTOR:    10
  },
  FRANCHISEE: {
    MAX_CASES: 5
  },
  DELAY: {
    RATE_LOAN:  14.6,
    RATE_LEASE: 14.6,
    ALERT_DAYS: 14
  },
  AFTER_SUPPORT: {
    SHAKEN_DAYS:       90,
    INSURANCE_DAYS:    90,
    SWITCH_DAYS:       365,
    SWITCH_NOTIFY_DAYS: 30,
    COMPLETION_DAYS:   90
  }
};

function getConfig() {
  return CONFIG;
}
