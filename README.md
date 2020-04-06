# Библиотека для работы с домофоном от domru.ru

> Для работы необходим refresh_token, который можно получить [через MITM proxy](https://mitmproxy.org/)

## Для получения токена необходимо:
1. Установить mitm proxy https://docs.mitmproxy.org/stable/overview-installation/
2. Настроить на телефоне проксирование трафика на установленную прокси. Необходимо настроить SSL проксирование.
3. Запустить приложение "Умный Дом.ru"
4. Найти в mitmproxy запрос
```
GET https://myhome.novotelecom.ru/auth/v2/session/refresh
```
В теле ответа будет 
```
"refreshToken": "xxxxxxxx-xxxxxxx-xxxx-xxxx-xxxx-xxxxxxxx"
```

Именно этот токен необходимо будет использовать для библиотеки

## Docker
```
docker run --name lib_domru -d -p 8080:8080 -e REFRESH_TOKEN=xxxxxxxx-xxxxxxx-xxxx-xxxx-xxxx-xxxxxxxx alexmorbo/domru:latest
```

... Где
- `--name lib_domru` - имя контейнера
- `-p 8080:8080` - порт, по которому будет доступен json api
- `-e REFRESH_TOKEN=xxxxxxxx-xxxxxxx-xxxx-xxxx-xxxx-xxxxxxxx` - токен, полученный ранее черещ mitm proxy

## Api

### Общая информация
```
HTTP GET http://host-ip:8080/
```
В ответе будет json с следующими параметрами:
- `accessToken` - текуший accessToken к API domru
- `memory` - количество памяти, которое использует библотека
- `finances` - блок с финансовой информацией от API
- `cameras` - блок с информацией о камерах, которые доступны через приложение и API
- `subscriberPlaces` - блок с информацией о пользователе, доступных домофонах
- `timers` - массив с информацией о внутренних таймерах

### Открыть дверь через домофон
```
HTTP GET http://host-ip:8080/open/{placeId}/{accessControlId}
```
Где:
- `placeId` - идентификатор места, берется из `subscriberPlaces`
- `accessControlId` - идентификатор домофона, берется из `subscriberPlaces`
> Если у вас всего 1 домофон на учетной записи, то передавать `placeId` и `accessControlId` не требуется 

В ответе будет json следующего вида:
```
{
    "status": true,
    "errorCode": null,
    "errorMessage": null
}
```

### Получить снепшот с камеры домофона
```
HTTP GET http://host-ip:8080/video/snapshot/{placeId}/{accessControlId}
```
Где:
- `placeId` - идентификатор места, берется из `subscriberPlaces`
- `accessControlId` - идентификатор домофона, берется из `subscriberPlaces`
> Если у вас всего 1 домофон на учетной записи, то передавать `placeId` и `accessControlId` не требуется 

В ответе будет image/jpeg с камеры домофона

### Ссылка на видеопоток с камры домофона
```
HTTP GET http://host-ip:8080/video/stream/{placeId}/{accessControlId}
```
Где:
- `placeId` - идентификатор места, берется из `subscriberPlaces`
- `accessControlId` - идентификатор домофона, берется из `subscriberPlaces`
> Если у вас всего 1 домофон на учетной записи, то передавать `placeId` и `accessControlId` не требуется 

В ответе будет json следующего вида:
```
{
    "url": "https://streamer2-zzzz.cctv.domru.ru:18080/rtsp/xxxxxx/yyyyyyyyy"
}
```
Где:
- `url` - ссылка на видео поток в формате `video/x-flv`
> Ссылка на видеопоток действует 1 раз!
>
> Для еще одного просмотра потока необходимо еще раз вызвать метод и получить новую ссылку!

