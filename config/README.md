# config/

配置**模板**目录。

- 只放 `*.example.php`
- 真实配置（`config/*.php`）已被 `.gitignore` 排除
- 上游 API Key 一律通过环境变量或 Web 根之外的文件注入
