# Yummy Diary 文件注释说明

本文档用于说明项目中每个主要文件的用途、职责和相互关系，方便答辩、维护和后续开发。

## 根目录

| 文件 | 说明 |
| --- | --- |
| `index.php` | 网站入口跳转文件。访问项目根目录时，会自动跳转到 `frontend/index.php`。 |
| `config.php` | 全站配置文件。负责读取 `.env` 环境变量、生成项目 URL、处理商品图片路径、设置错误处理，并建立 PDO 数据库连接。 |
| `.htaccess` | Apache URL 重写规则。提供干净网址，例如 `/shop`、`/contact`、`/receipt`、`/search`，并兼容旧路径。 |
| `.env.example` | 环境变量模板。用于说明数据库连接和运行环境需要配置哪些字段。 |
| `database-install-latest.sql` | 数据库完整安装或备份 SQL 文件。适合初始化数据库时使用。 |

## 前台 Frontend

| 文件 | 说明 |
| --- | --- |
| `frontend/index.php` | 前台首页。负责展示品牌首页内容、清理购物车状态、记录访客 token，并加载共享 header/footer。 |
| `frontend/shop.php` | 商品商店页面。负责显示商品列表、分类筛选、商品卡片和加入购物车入口。 |
| `frontend/contact.php` | 联系页面。展示店铺联系方式、品牌信息或联系入口。 |
| `frontend/receipt.php` | 订单收据页面。用户结算后进入，用于展示订单编号、订单内容和付款确认状态。 |
| `frontend/hardware/header.php` | 前台共享页头。包含 Logo、导航菜单、搜索框和搜索建议请求逻辑。 |
| `frontend/hardware/footer.php` | 前台共享页脚和购物车浮窗。包含购物车弹窗、数量更新、清空购物车、结算按钮和前端交互脚本。 |

## 前台 API

| 文件 | 说明 |
| --- | --- |
| `frontend/api/add_to_cart.php` | 购物车 API。处理加入购物车、获取购物车、增加/减少数量、删除商品、清空购物车等操作。 |
| `frontend/api/checkout.php` | 结账 API。根据购物车内容生成订单，并返回收据页地址。 |
| `frontend/api/confirm_payment.php` | 付款确认 API。用于确认订单付款状态或更新付款流程。 |
| `frontend/api/search.php` | 搜索结果页面/API。根据关键词查询商品并展示结果。 |
| `frontend/api/search_suggest.php` | 搜索建议 API。用户输入关键词时，返回匹配的商品建议。 |

## 后台 Admin

| 文件 | 说明 |
| --- | --- |
| `backend/login.php` | 管理员登录页面。验证管理员账号和密码，登录成功后建立 admin session。 |
| `backend/logout.php` | 管理员退出登录。清理后台 session 并返回登录页。 |
| `backend/auth_admin.php` | 后台权限保护文件。后台页面引用它来确保用户已登录，否则跳转到登录页。 |
| `backend/dashboard.php` | 后台仪表盘。显示销售、订单、访客或管理概览。 |
| `backend/products.php` | 商品管理页面。用于查看商品列表，并进入新增、编辑、删除等管理操作。 |
| `backend/add_product.php` | 新增商品页面。用于录入商品资料、价格、图片和库存相关信息。 |
| `backend/edit_product.php` | 编辑商品页面。用于修改已存在商品的基本资料、图片和变体。 |
| `backend/product_sort.php` | 商品排序页面。用于调整商品或分类在前台展示的顺序。 |
| `backend/inventory.php` | 库存管理页面。用于检查和更新 SKU、商品变体或库存数量。 |
| `backend/orders.php` | 订单管理页面。用于查看订单、处理付款状态和订单履约状态。 |
| `backend/promotions.php` | 优惠管理页面。用于创建、编辑和管理促销规则。 |
| `backend/includes/sidebar.php` | 后台共享侧边栏。提供桌面端侧边导航和移动端底部导航。 |
| `backend/css/admin_layout.css` | 后台布局样式。控制后台页面整体排版、侧边栏和移动端布局。 |
| `backend/.htaccess` | 后台目录 Apache 规则。用于控制后台目录访问和默认行为。 |

## 后台 API

| 文件 | 说明 |
| --- | --- |
| `backend/api/product_api.php` | 商品管理 API。为后台商品管理、排序或商品数据更新提供接口。 |
| `backend/api/orders_api.php` | 订单管理 API。为后台订单状态更新、订单查询或处理操作提供接口。 |
| `backend/api/visitors_api.php` | 访客统计 API。为后台仪表盘提供访客数据。 |

## 样式文件

| 文件 | 说明 |
| --- | --- |
| `css/style.css` | 前台主要样式文件。控制首页、商店、联系页、商品卡片和通用视觉风格。 |
| `css/sidebar.css` | 侧边栏或辅助导航样式。用于特定页面的侧栏布局。 |

## 数据库脚本

| 文件 | 说明 |
| --- | --- |
| `database/create-admin.sql` | 创建管理员账号或管理员表相关 SQL。 |
| `database/20260613_product_variants.sql` | 商品变体相关数据库迁移，例如 SKU、规格、库存等结构。 |
| `database/restore-category-groups.sql` | 分类分组恢复脚本，用于还原商品分类或分组资料。 |

## 设计与文档

| 文件 | 说明 |
| --- | --- |
| `docs/frontend-sitemap.svg` | 前台网站 sitemap 图。展示用户端页面、购物车、结账、搜索和共享组件。 |
| `docs/admin-sitemap.svg` | 后台网站 sitemap 图。展示管理员登录、仪表盘、商品、库存、订单和优惠管理结构。 |
| `docs/file-annotations.md` | 当前文件。作为项目文件结构和注释说明文档。 |

## 维护建议

- `images/` 和 `frontend/uploads/` 已按要求清空。如果系统仍引用旧图片，需要重新上传或替换路径。
- `.env` 应只保留在本地，不建议提交到版本库。
- 新功能建议按现有结构放置：前台页面放 `frontend/`，前台接口放 `frontend/api/`，后台页面放 `backend/`，后台接口放 `backend/api/`。
