# Entrega 1 — Tema, Descrição do Sistema, RF e RNF

## Vídeo de Apresentação

[//]: # (> **TODO:** link do YouTube. Regras: todos os integrantes aparecem na webcam e)

[//]: # (> falam; ~5 min; seguir a ordem dos itens da planilha de notas; conferir o áudio)

[//]: # (> de cada integrante.)

## Tema do Sistema

Eventos acadêmicos do campus — palestras, oficinas, semanas acadêmicas e minicursos.

Hoje são divulgados de forma dispersa. Cartazes, grupos, mensagens. Alunos perdem eventos por não saber que os eventos
existem.

Organizadores não têm controle de inscrições. Não têm previsão de público. Utilizam ferramentas improvisadas (Google
Forms, por exemplo) e não unificadas entre diferentes grupos de organizadores.

[//]: # (Exemplo: terça, dia 25-aug, nem todos os alunos do curso de TSI e presentes na aula de CTS sabiam do evento divulgado via discord.
[//]: # (Por um lado discord é flexível, por outro não se oferece como um canal oficial de eventos. É generalista. )

## Descrição do Sistema

O sistema **Eventos do Campus** objetiva apoiar a divulgação centralizada dos eventos do campus. Facilitar o processo de
inscrição dos participantes e oferecer aos organizadores a possibilidade de um ponto único de cadastro e acompanhamento.

## Impacto Esperado

- Para os participantes: um único lugar para descobrir eventos e confirmar presença, reduzindo eventos perdidos por
  falta de divulgação.
- Para os organizadores: lista de inscritos em tempo real, apoiando decisões de espaço, material e certificados.
- Para o campus: histórico centralizado dos eventos realizados e do público alcançado, apoiando a divulgação das
  próximas edições.

## Requisitos Funcionais

- RF01 – O sistema deverá permitir que o usuário realize seu cadastro informando nome, e-mail e senha.
- RF02 – O sistema deverá permitir que o usuário se autentique com e-mail e senha.
- RF03 – O sistema deverá restringir a área administrativa a usuários com privilégio de administrador.
- RF04 – O sistema deverá permitir que o administrador cadastre, edite e remova eventos, informando título, descrição,
  data, local e número de vagas.
- RF05 - O sistema deverá permitir que o participante se inscreva em um evento.
- RF06 - O sistema deverá impedir mais de uma inscrição do mesmo participante no mesmo evento.

## Requisitos Não Funcionais

- RNF01 – O sistema deverá apresentar interface responsiva, permitindo sua utilização em computadores, tablets e
  smartphones (grid do Bootstrap).
- RNF02 – As senhas deverão ser armazenadas exclusivamente como hash.
- RNF03 - O sistema deverá ser executável com um único comando via Docker Compose.
- As listagens deverão ser paginadas em 10 itens por página, mantendo o tempo de resposta das páginas abaixo de 2
  segundos.

## Requisitos Técnicos

- Docker e Docker Compose para padronizar o ambiente de execução.
- PHPUnit (testes unitários) e Codeception (testes de aceitação).
- PHP orientado a objetos com o mini-framework da disciplina (MVC)
- MySQL
