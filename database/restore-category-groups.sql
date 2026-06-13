-- Restore the original storefront category groups.

INSERT INTO `category_groups` (`group_key`, `label`, `sort_order`, `status`)
VALUES
  ('snacks', '速食小吃', 1, 1),
  ('meals', '粉类/速食主食', 2, 1),
  ('candy', '糖果', 3, 1),
  ('chips', '脆片坚果类', 4, 1),
  ('creative', '文创小物', 5, 1)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`),
  `status` = 1;

SET @snacks_id = (SELECT `id` FROM `category_groups` WHERE `group_key`='snacks' LIMIT 1);
SET @meals_id = (SELECT `id` FROM `category_groups` WHERE `group_key`='meals' LIMIT 1);
SET @candy_id = (SELECT `id` FROM `category_groups` WHERE `group_key`='candy' LIMIT 1);
SET @chips_id = (SELECT `id` FROM `category_groups` WHERE `group_key`='chips' LIMIT 1);
SET @creative_id = (SELECT `id` FROM `category_groups` WHERE `group_key`='creative' LIMIT 1);

UPDATE `product_categories`
SET `group_id`=@snacks_id,
    `name`=CASE `category_key`
      WHEN 'moyu' THEN '魔芋爽'
      WHEN 'xieliu' THEN '蟹柳'
      WHEN 'egg' THEN '鹌鹑蛋'
      WHEN 'tofu' THEN '鱼豆腐'
      WHEN 'latiao' THEN '辣条'
      WHEN 'jinzhen' THEN '金针菇'
      WHEN 'tudoupian' THEN '土豆片'
      WHEN 'lianou' THEN '莲藕片'
      WHEN 'moyu2' THEN '魔芋'
      WHEN 'haidai' THEN '海带'
      WHEN 'other' THEN '其他'
    END,
    `sort_order`=FIELD(`category_key`,
      'moyu','xieliu','egg','tofu','latiao','jinzhen',
      'tudoupian','lianou','moyu2','haidai','other')
WHERE `category_key` IN (
  'moyu','xieliu','egg','tofu','latiao','jinzhen',
  'tudoupian','lianou','moyu2','haidai','other'
);

UPDATE `product_categories`
SET `group_id`=@meals_id,
    `name`=CASE `category_key`
      WHEN 'noodle' THEN '酸辣粉'
      WHEN 'luosifen' THEN '螺蛳粉'
      WHEN 'hotpot' THEN '自热火锅'
    END,
    `sort_order`=FIELD(`category_key`,'noodle','luosifen','hotpot')
WHERE `category_key` IN ('noodle','luosifen','hotpot');

UPDATE `product_categories`
SET `group_id`=@candy_id,
    `name`=CASE `category_key`
      WHEN 'qqcandy' THEN 'QQ糖果'
      WHEN 'coffee' THEN '咖啡糖'
      WHEN 'other1' THEN '其他'
    END,
    `sort_order`=FIELD(`category_key`,'qqcandy','coffee','other1')
WHERE `category_key` IN ('qqcandy','coffee','other1');

UPDATE `product_categories`
SET `group_id`=@chips_id,
    `name`=CASE `category_key`
      WHEN 'lays' THEN 'Lays 薯片'
      WHEN 'other2' THEN '其他'
    END,
    `sort_order`=FIELD(`category_key`,'lays','other2')
WHERE `category_key` IN ('lays','other2');

UPDATE `product_categories`
SET `group_id`=@creative_id,
    `name`='文创小物',
    `sort_order`=1
WHERE `category_key`='creative';
