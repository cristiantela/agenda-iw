## POST /users

Creates an user
**Body**

- name
- email
- password

## POST /users/sessions

Creates an user session (login)
**Body**

- email
- password

## POST /events

Creates an event
Requires token (Authorization)
**Body**

- title
- description
- start_date
- end_date (_optional_)
- relations (_array_)
  _ type (user|group)
  _ id

## POST /groups

Creates a group
Requires token (Authorization)
**Body**

- name
- users (_array of numbers_)

## GET /events

_Requires token (Authorization)_
Get all related events

## GET /search

_Requires token (Authorization)_
Get all user with `name` matched
**Query**

- name
