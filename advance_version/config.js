module.exports = {
  port: Number(process.env.PORT) || 3000,
  sessionSecret: process.env.SESSION_SECRET || 'jnb-clinic-dev-secret',
  siteBase: process.env.SITE_BASE || 'http://localhost:3000',
  db: {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'jnb_clinic_advance',
    connectionLimit: 10,
  },
  mail: {
    host: process.env.SMTP_HOST || 'localhost',
    port: Number(process.env.SMTP_PORT) || 25,
    from: process.env.MAIL_FROM || 'jnb@localhost.net',
    allowedDomains: ['localhost', 'localhost.net', 'localhost.org', 'localhost.com'],
  },
};
