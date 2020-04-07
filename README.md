# Библиотека для работы с домофоном от domru.ru

> Для работы необходим access_token, который можно получить из COOKIES после авторизации в личном кабинете domru.ru

## Для получения токена необходимо:
Пример будет реализован на Google Chrome
1. Автоизоваться в личном кабинете https://domru.ru/
2. Открыть [Google Chrome Dev Tools](https://developers.google.com/web/tools/chrome-devtools/open)
3. Открыть вкладку Console и ввести внизу следующий код
```
function getCookie(name) {
  let matches = document.cookie.match(new RegExp(
    "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
  ));
  return matches ? decodeURIComponent(matches[1]) : undefined;
}
getCookie('ACCESS_TOKEN')
```
4. В ответ Вы получите 30-значный access_token

Именно этот токен необходимо будет использовать для библиотеки

## Docker
```
docker run --name lib_domru -d -p 8080:8080 -e ACCESS_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx alexmorbo/domru:latest
```

... Где
- `--name lib_domru` - имя контейнера
- `-p 8080:8080` - порт, по которому будет доступен json api
- `-e ACCESS_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` - токен, полученный ранее

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

### Видеопоток с камры домофона
```
HTTP GET http://host-ip:8080/video/stream/{placeId}/{accessControlId}
```
Где:
- `placeId` - идентификатор места, берется из `subscriberPlaces`
- `accessControlId` - идентификатор домофона, берется из `subscriberPlaces`
> Если у вас всего 1 домофон на учетной записи, то передавать `placeId` и `accessControlId` не требуется 

Ответом от сервиса будет перенаправление на ссылку, по которой можно получить видеопоток с камеры
