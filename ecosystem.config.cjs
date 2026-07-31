module.exports = {
    apps: [
      {
        name: 'socket-server',
        cwd: '/home/admins/public_html/socket-server',
        script: 'server.js',
        interpreter: 'node',
        instances: 1,
        exec_mode: 'fork',
        autorestart: true,
        max_memory_restart: '400M',
        kill_timeout: 5000,
        env: {
          NODE_ENV: 'production',
        },
      },
      {
        name: 'duel-bot',
        cwd: '/home/admins/public_html',
        script: '/home/admins/public_html/artisan',
        args: 'duel:bot --auto --timeout=7200 --delay=1.5',
        interpreter: 'php',
        instances: 1,
        exec_mode: 'fork',
        autorestart: true,
        // zend_mm_heap / uzun CLI: bellek eşiğinde temiz recycle (socket'e dokunmaz)
        max_memory_restart: '250M',
        kill_timeout: 5000,
        restart_delay: 2000,
        exp_backoff_restart_delay: 1000,
      },
    ],
  };
