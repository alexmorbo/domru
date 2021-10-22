# Home Assistant Community Add-on: Умный Дом.ru

## Что это?

Это дополнение (Add-on) для Home Assistant, которое позволяет интегрировать умный домофон от Дом.ru в систему Home Assistant

## Установка

Для установки необоходимо в зайти в Home Assistant -> Supervisor -> Add-on Store -> Menu -> Repositories

В появившемся окне необходимо добавить: `https://github.com/alexmorbo/domru` и нажать `Add`

После установки необходимо зайти в Web UI (Open WEB UI) и авторизоваться с помощью телефона и смс.


![Lovelace](domru/lovelace.png)

## FAQ
### Есть ли возможность отслеживать входящий звонок?
Нет. Звонки идут через пуш уведомления, их отловить нет возможности.  
Для этого можно использовать аппаратное решение, например:  
Wi-Fi - https://t.me/domofon_esp  
Zigbee - https://t.me/zigbeat/1172
