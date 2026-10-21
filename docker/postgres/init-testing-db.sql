SELECT 'CREATE DATABASE decisao_investimento_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'decisao_investimento_testing')\gexec
