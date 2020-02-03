# Agenda IW (Internet & Web)

Projeto desenvolvido para a disciplina de Internet & Web.

**_Escopo do projeto_**
Este sistema será um espaço para as pessoas poderem adicionar eventos e atividades a sua agenda, podendo fazer isto de forma compartilhada entre grupos e pessoas.

**Tecnologias:**

- MySQL: para o armazenamento de dados;
- PHP com Slim: para a interface do front com os dados do banco, a API do sistema;
- Vue.js: para facilitar a manipulação da visualização dos dados no navegador.

## Execução do projeto

1. Ao clonar este repositório, faça a importação do banco de dados `agendaiw`, presente no arquivo `agendaiw.sql`, na pasta raiz, para sua base de dados MySQL.
2. Altere no arquivo `api/index.php`, se necessário, os dados de configuração para conexão com a base de dados (`host`, `user`, `pass`, `dbname`);
3. Por fim, acesse, por meio de um servidor (pode ser local), a página principal (`index.html`).
