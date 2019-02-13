# DDLE
Скрипт для рандомизации дат постов DLE

## Требования
Установленная DLE

## Установка

Скопируйте файл [ddle.php](https://raw.githubusercontent.com/orlov0562/ddle/master/ddle.php) в корень сайта, туда где у вас index.php

## Использование

Перейдите по адресу **site.com/ddle.php***
Введите логин/пароль (по-умолчанию: **admin / ddle**)
Используйте :)

## Настройки
Логин пароль можно поменять открыв файл в редакторе, в самом верху, 
формат <логин> => <пароль>
```
    $httpBasicAuthAllowedUsers = [ // <username> => <password>
        'admin' => 'ddle'
    ];
```
Полностью отключить авторизацию, можно установив
```
$useHttpBasicAuth = false;
```

## Безопасность
Лучше всего стирать этот файл после использования.
Если оставляете на продолжительное время, включайте авторизацию (по-умолчанию включена) и меняйте логин/пароль.

## Обсуждение
Обсуждение скрипта [тут](https://www.it-rem.ru/paketnoe-izmenenie-datyi-dlya-postov-dle.html)

## Скриншот
![Screenshot](/screenshot.png)

## Обновления
- **1.0.1** Добавлено обновление дат комментариев к посту, относительно новой даты поста. Интервал между датами комментариев можно указать в расширенных настройках.
- **1.0.0** Первоначальная версия
