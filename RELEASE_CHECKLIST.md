# Release checklist

Перед загрузкой на сервер:

- [ ] заменить заглушки проектов в `assets/img/projects/` на реальные фото;
- [ ] если есть видео-loop, положить их в `assets/video/projects/` и прописать поле `video` в `data/projects.php`;
- [ ] проверить email/Telegram/локацию в `data/contacts.php`;
- [ ] проверить мета-описание в `index.php`;
- [ ] после деплоя открыть сайт на desktop и телефоне;
- [ ] проверить dark/light mode;
- [ ] проверить кнопки «Показать больше», «Показать ещё проекты», «Читать больше», «Связаться»;
- [ ] проверить, что `robots.txt` и `sitemap.xml` открываются с домена;
- [ ] подключить HTTPS через certbot на reverse proxy или на самом контейнере.
