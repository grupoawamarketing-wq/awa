<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block\VerticalMenu;

use Magento\Framework\UrlInterface;

class SafeVerticalmenu extends \Rokanthemes\VerticalMenu\Block\Verticalmenu
{
    /**
     * Allow only safe tokens for CSS classes (single token).
     */
    private function sanitizeClassToken(?string $value): string
    {
        $value = (string)$value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';

        return trim($value);
    }

    /**
     * Allow only safe tokens for CSS class lists (space separated).
     */
    private function sanitizeClassList(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $value) ?: [];
        $safe = [];

        foreach ($tokens as $token) {
            $token = $this->sanitizeClassToken($token);
            if ($token !== '') {
                $safe[] = $token;
            }
        }

        return implode(' ', array_values(array_unique($safe)));
    }

    /**
     * Allow only safe css sizes (prevents style injection).
     * Examples allowed: 500px, 80%, 20rem, 10em, 50vw
     */
    private function sanitizeCssSize(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,4}(px|%|rem|em|vw)$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function clampGridColumns(int $value): int
    {
        return max(0, min(12, $value));
    }

    /**
     * Render submenu level 1 with Mueller-compatible semantic classes.
     *
     * @param mixed $category
     * @param mixed $categoryModel
     * @param array $children
     */
    private function getMuellerLevelOneItemsHtml(
        $category,
        $categoryModel,
        array $children,
        int $maxLevel,
        int $columnWidth,
        string $menuType,
        int $columns,
        string $parentMenuToken
    ): string {
        $html = '';

        if ($maxLevel > 0 && $maxLevel - 1 < 1) {
            return $html;
        }

        $columnClass = '';
        if (($menuType === 'fullwidth' || $menuType === 'staticwidth') && $columns > 0) {
            $safeColumns = $this->sanitizeClassToken((string)$columns);
            $safeColumnWidth = $this->clampGridColumns($columnWidth);
            $columnClass = 'col-sm-' . $safeColumnWidth . ' mega-columns columns' . $safeColumns;
        }

        $listClass = 'subchildmenu navigation__inner-list navigation__inner-list--level1';
        if ($columnClass !== '') {
            $listClass .= ' ' . $columnClass;
        }

        $categoryName = $this->escapeHtml($category->getName());
        $categoryNameAttr = $this->escapeHtmlAttr($category->getName());
        $categoryUrl = $this->escapeUrl($this->_categoryHelper->getCategoryUrl($category));

        $html .= '<ul class="' . $this->escapeHtmlAttr(trim($listClass)) . '"'
            . ' data-level="1"'
            . ' data-menu-id="' . $this->escapeHtmlAttr($parentMenuToken . '-children') . '"'
            . ' data-parent-id="' . $this->escapeHtmlAttr($parentMenuToken) . '"'
            . ' data-menu-title="' . $categoryNameAttr . '">';
        $html .= '<li class="navigation__inner-item navigation__inner-item--level1 subcategory-title col-1">';
        $html .= '<span>' . $categoryName . '</span>';
        $html .= '</li>';

        foreach ($children as $child) {
            $childModel = $this->getCategoryModel($child->getId());
            $hideItem = (bool)$childModel->getData('vc_menu_hide_item');

            if ($hideItem) {
                continue;
            }

            $subChildren = $this->getActiveChildCategories($child);
            $hasChildren = count($subChildren) > 0;

            $urlToken = $this->sanitizeClassToken((string)$child->getUrlKey());
            $classParts = [
                'ui-menu-item',
                'level1',
                'navigation',
                'navigation__inner-item',
                'navigation__inner-item--level1',
                'subcategory-second-level',
                'col-1',
                'parent-ul-cat-mega-menu'
            ];

            if ($urlToken !== '') {
                $classParts[] = $urlToken;
            }

            if ($hasChildren) {
                $classParts[] = 'parent';
                $classParts[] = 'navigation__inner-item--parent';
                $classParts[] = 'position-anchor';
            }

            $vcMenuCatLabel = (string)$childModel->getData('vc_menu_cat_label');
            $vcMenuFontIcon = (string)$childModel->getData('vc_menu_font_icon');
            $vcMenuIconImg = $this->_helper->getVerticalIconimageUrl($childModel);
            $menuToken = 'menu-' . (int)$child->getId();
            $childName = $this->escapeHtml($child->getName());
            $childNameAttr = $this->escapeHtmlAttr($child->getName());
            $childUrl = $this->escapeUrl($this->_categoryHelper->getCategoryUrl($child));

            $html .= '<li class="' . $this->escapeHtmlAttr(implode(' ', array_values(array_unique($classParts)))) . '"'
                . ' data-level="1"'
                . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-parent-id="' . $this->escapeHtmlAttr($parentMenuToken) . '"'
                . ' data-menu-title="' . $childNameAttr . '"'
                . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                . '>';

            if ($hasChildren) {
                $html .= '<div class="open-children-toggle navigation__toggle" role="button" tabindex="0" aria-label="Expandir subcategorias" aria-expanded="false"></div>';
            }

            $html .= '<a class="navigation__inner-link title-cat-mega-menu"'
                . ' href="' . $childUrl . '"'
                . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-parent-id="' . $this->escapeHtmlAttr($parentMenuToken) . '"'
                . ' data-menu-title="' . $childNameAttr . '"'
                . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                . '>';

            if ($vcMenuIconImg) {
                $iconImgUrl = (string)$childModel->getImageUrl('vc_menu_icon_img');
                if ($iconImgUrl !== '') {
                    $html .= '<img class="menu-thumb-icon awa-mueller-icon awa-mueller-icon--image" src="' . $this->escapeUrl($iconImgUrl) . '" alt="' . $childNameAttr . '" loading="lazy"/>';
                }
            } else {
                $iconClass = $this->sanitizeClassList($vcMenuFontIcon);
                if ($iconClass !== '') {
                    $html .= '<em class="' . $this->escapeHtmlAttr('menu-thumb-icon awa-mueller-icon awa-mueller-icon--font ' . $iconClass) . '"></em>';
                }
            }

            $html .= '<span class="navigation__label">' . $childName;

            if ($vcMenuCatLabel !== '' && isset($this->_verticalmenuConfig['cat_labels'][$vcMenuCatLabel])) {
                $labelKey = $this->sanitizeClassToken($vcMenuCatLabel);
                $labelText = $this->escapeHtml((string)$this->_verticalmenuConfig['cat_labels'][$vcMenuCatLabel]);
                $html .= '<span class="cat-label cat-label-' . $this->escapeHtmlAttr($labelKey) . '">' . $labelText . '</span>';
            }

            $html .= '</span>';
            $html .= '</a>';

            if ($hasChildren) {
                $html .= $this->getSubmenuItemsHtml($subChildren, 2, $maxLevel, $columnWidth, $menuType, null, $menuToken);
            }

            $html .= '</li>';
        }

        $categoryImage = (string)$categoryModel->getData('image');
        if ($categoryImage !== '') {
            try {
                $categoryImageUrl = (string)$categoryModel->getImageUrl('image');
            } catch (\Exception $e) {
                $store = $this->_storeManager->getStore();
                $mediaUrl = '';
                if (is_object($store) && method_exists($store, 'getBaseUrl')) {
                    $mediaUrl = (string)call_user_func([$store, 'getBaseUrl'], UrlInterface::URL_TYPE_MEDIA);
                }
                $categoryImageUrl = $mediaUrl . 'catalog/category/' . ltrim($categoryImage, '/');
            }

            if ($categoryImageUrl !== '') {
                $html .= '<li class="navigation__inner-item navigation__inner-item--level1 imagem img-subcategory col-2">';
                $html .= '<a href="' . $categoryUrl . '" class="navigation__inner-link">';
                $html .= '<img loading="lazy" src="' . $this->escapeUrl($categoryImageUrl) . '" alt="' . $categoryNameAttr . '" />';
                $html .= '</a>';
                $html .= '</li>';
            }
        }

        $html .= '<li class="navigation__inner-item navigation__inner-item--all navigation__inner-item--level1 hb-strong-title">';
        $html .= '<a href="' . $categoryUrl . '" class="navigation__inner-link">';
        $html .= $this->escapeHtml(__('Ver tudo'));
        $html .= '</a>';
        $html .= '</li>';

        $html .= '</ul>';

        return $html;
    }

    /**
     * @inheritDoc
     */
    public function getSubmenuItemsHtml($children, $level = 1, $max_level = 0, $column_width = 12, $menu_type = 'fullwidth', $columns = null, $parentMenuId = 'root')
    {
        $html = '';

        if (!$max_level || ($max_level && $max_level == 0) || ($max_level && $max_level > 0 && $max_level - 1 >= $level)) {
            $column_class = '';

            if ($level == 1 && $columns && ($menu_type == 'fullwidth' || $menu_type == 'staticwidth')) {
                $columnWidth = $this->clampGridColumns((int)$column_width);
                $columnsSafe = $this->sanitizeClassToken((string)$columns);

                $column_class = 'col-sm-' . $columnWidth . ' ';
                $column_class .= 'mega-columns columns' . $columnsSafe;
            }

            $listClasses = 'subchildmenu navigation__inner-list navigation__inner-list--level' . (int)$level;
            if (trim($column_class) !== '') {
                $listClasses .= ' ' . trim($column_class);
            }

            $html = '<ul class="' . $this->escapeHtmlAttr(trim($listClasses)) . '"'
                . ' data-level="' . (int)$level . '"'
                . ' data-menu-id="' . $this->escapeHtmlAttr((string)$parentMenuId . '-level-' . (int)$level) . '"'
                . ' data-parent-id="' . $this->escapeHtmlAttr((string)$parentMenuId) . '">';

            foreach ($children as $child) {
                $cat_model = $this->getCategoryModel($child->getId());

                $vc_menu_hide_item = $cat_model->getData('vc_menu_hide_item');

                if ($vc_menu_hide_item) {
                    continue;
                }

                $sub_children = $this->getActiveChildCategories($child);

                $vc_menu_cat_label = (string)$cat_model->getData('vc_menu_cat_label');
                $vc_menu_font_icon = (string)$cat_model->getData('vc_menu_font_icon');
                $childId = (int)$child->getId();
                $menuToken = 'menu-' . $childId;
                $childName = $this->escapeHtml($child->getName());
                $childNameAttr = $this->escapeHtmlAttr($child->getName());

                $item_class = 'level' . (int)$level . ' ';
                $item_class .= 'navigation__inner-item navigation__inner-item--level' . (int)$level . ' ';
                if ((int)$level === 2) {
                    $item_class .= 'subcategory-second-level col-1 ';
                }

                $hasChildren = count($sub_children) > 0;
                if ($hasChildren) {
                    $item_class .= 'parent navigation__inner-item--parent position-anchor ';
                }

                $linkClasses = ['navigation__inner-link'];
                if ($level == 1 && ($menu_type == 'fullwidth' || $menu_type == 'staticwidth')) {
                    $linkClasses[] = 'title-cat-mega-menu';
                    $item_class .= 'parent-ul-cat-mega-menu';
                    $item_class .= 'subcategory-second-level col-1 ';
                }

                /* --- Phase C: product count + category image as data attrs --- */
                $dataAttrs = '';
                if ($level === 1) {
                    try {
                        $productCount = (int) $cat_model->getProductCount();
                    } catch (\Exception $e) {
                        $productCount = 0;
                    }
                    $dataAttrs .= ' data-product-count="' . $productCount . '"';

                    $catImage = (string) $cat_model->getData('image');
                    if ($catImage !== '') {
                        try {
                            $catImageUrl = (string) $cat_model->getImageUrl('image');
                        } catch (\Exception $e) {
                            $store = $this->_storeManager->getStore();
                            $mediaUrl = '';
                            if (is_object($store) && method_exists($store, 'getBaseUrl')) {
                                $mediaUrl = (string) call_user_func(
                                    [$store, 'getBaseUrl'],
                                    \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                                );
                            }
                            $catImageUrl = $mediaUrl . 'catalog/category/' . ltrim($catImage, '/');
                        }
                        if ($catImageUrl !== '') {
                            $dataAttrs .= ' data-cat-image="' . $this->escapeUrl($catImageUrl) . '"';
                        }
                    }
                }

                $html .= '<li class="ui-menu-item ' . $this->escapeHtmlAttr(trim($item_class)) . '"'
                    . $dataAttrs
                    . ' data-level="' . (int)$level . '"'
                    . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                    . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                    . ' data-parent-id="' . $this->escapeHtmlAttr((string)$parentMenuId) . '"'
                    . ' data-menu-title="' . $childNameAttr . '"'
                    . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                    . '>';

                if ($hasChildren) {
                    $html .= '<div class="open-children-toggle navigation__toggle" role="button" tabindex="0" aria-label="Expandir subcategorias" aria-expanded="false"></div>';
                }

                $childUrl = $this->escapeUrl($this->_categoryHelper->getCategoryUrl($child));
                $linkClassAttr = ' class="' . $this->escapeHtmlAttr(implode(' ', $linkClasses)) . '"';

                $html .= '<a' . $linkClassAttr
                    . ' href="' . $childUrl . '"'
                    . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                    . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                    . ' data-parent-id="' . $this->escapeHtmlAttr((string)$parentMenuId) . '"'
                    . ' data-menu-title="' . $childNameAttr . '"'
                    . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                    . '>';

                $iconClass = $this->sanitizeClassList($vc_menu_font_icon);
                if ($iconClass !== '') {
                    $html .= '<em class="' . $this->escapeHtmlAttr('menu-thumb-icon awa-mueller-icon awa-mueller-icon--font ' . $iconClass) . '"></em>';
                }

                $html .= '<span class="navigation__label">' . $childName;

                if ($vc_menu_cat_label !== '' && isset($this->_verticalmenuConfig['cat_labels'][$vc_menu_cat_label])) {
                    $labelKey = $this->sanitizeClassToken($vc_menu_cat_label);
                    $labelText = $this->escapeHtml((string)$this->_verticalmenuConfig['cat_labels'][$vc_menu_cat_label]);
                    $html .= '<span class="cat-label cat-label-' . $this->escapeHtmlAttr($labelKey) . '">' . $labelText . '</span>';
                }

                $html .= '</span></a>';

                if (count($sub_children) > 0) {
                    $html .= $this->getSubmenuItemsHtml($sub_children, $level + 1, $max_level, $column_width, $menu_type, null, $menuToken);
                }

                $html .= '</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * @inheritDoc
     */
    public function getVerticalMenuHtml()
    {
        $html = '';

        $categories = $this->getStoreCategories(true, false, true);

        $this->_verticalmenuConfig = $this->_helper->getConfig('verticalmenu');
        $max_level = $this->_verticalmenuConfig['general']['max_level'] ?? 0;

        $html .= $this->getCustomBlockHtml('before');

        foreach ($categories as $category) {
            if (!$category->getIsActive()) {
                continue;
            }

            $cat_model = $this->getCategoryModel($category->getId());

            $vc_menu_hide_item = $cat_model->getData('vc_menu_hide_item');
            if ($vc_menu_hide_item) {
                continue;
            }

            $children = $this->getActiveChildCategories($category);

            $vc_menu_cat_label = (string)$cat_model->getData('vc_menu_cat_label');
            $vc_menu_font_icon = (string)$cat_model->getData('vc_menu_font_icon');
            $vc_menu_cat_columns = (int)$cat_model->getData('vc_menu_cat_columns');
            $vc_menu_float_type = (string)$cat_model->getData('vc_menu_float_type');

            if (!$vc_menu_cat_columns) {
                $vc_menu_cat_columns = 4;
            }

            $menu_type = (string)$cat_model->getData('vc_menu_type');
            if ($menu_type === '') {
                $menu_type = (string)($this->_verticalmenuConfig['general']['menu_type'] ?? 'fullwidth');
            }

            $custom_style = '';
            if ($menu_type === 'staticwidth') {
                $size = $this->sanitizeCssSize((string)$cat_model->getData('vc_menu_static_width'))
                    ?: '500px';
                $custom_style = ' style="width: ' . $this->escapeHtmlAttr($size) . ';"';
            }

            $categoryToken = $this->sanitizeClassToken((string)$category->getUrlKey());
            $item_class = 'level0 navigation__item navigation__item--level0 category category-tree position-anchor subcategory-first-level '
                . $this->sanitizeClassToken($menu_type) . ' ';
            if ($categoryToken !== '') {
                $item_class .= $categoryToken . ' ';
            }

            $menu_top_content = (string)$cat_model->getData('vc_menu_block_top_content');
            $menu_left_content = (string)$cat_model->getData('vc_menu_block_left_content');
            $menu_left_width = (int)$cat_model->getData('vc_menu_block_left_width');
            if ($menu_left_content === '' || !$menu_left_width) {
                $menu_left_width = 0;
            }

            $menu_right_content = (string)$cat_model->getData('vc_menu_block_right_content');
            $menu_right_width = (int)$cat_model->getData('vc_menu_block_right_width');
            if ($menu_right_content === '' || !$menu_right_width) {
                $menu_right_width = 0;
            }

            $menu_bottom_content = (string)$cat_model->getData('vc_menu_block_bottom_content');

            $floatType = '';
            if ($vc_menu_float_type !== '') {
                $floatType = 'fl-' . $this->sanitizeClassToken($vc_menu_float_type) . ' ';
            }

            $hasMegaContent = ($menu_type === 'fullwidth' || $menu_type === 'staticwidth')
                && ($menu_top_content !== '' || $menu_left_content !== '' || $menu_right_content !== '' || $menu_bottom_content !== '');

            $hasChildren = count($children) > 0 || $hasMegaContent;
            if ($hasChildren) {
                $item_class .= 'parent navigation__item--parent ';
            }
            $menuToken = 'menu-' . (int)$category->getId();

            $categoryUrl = $this->escapeUrl($this->_categoryHelper->getCategoryUrl($category));
            $categoryName = $this->escapeHtml($category->getName());
            $categoryNameAttr = $this->escapeHtmlAttr($category->getName());

            $html .= '<li class="ui-menu-item ' . $this->escapeHtmlAttr(trim($item_class . $floatType)) . '"'
                . ' data-level="0"'
                . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-parent-id="root"'
                . ' data-menu-title="' . $categoryNameAttr . '"'
                . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                . '>';

            if ($hasChildren) {
                $html .= '<div class="open-children-toggle navigation__toggle" role="button" tabindex="0" aria-label="Expandir subcategorias" aria-expanded="false"></div>';
            }

            $html .= '<a href="' . $categoryUrl . '" class="level-top navigation__link"'
                . ' data-menu="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                . ' data-parent-id="root"'
                . ' data-menu-title="' . $categoryNameAttr . '"'
                . ($hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '')
                . '>';

            $vc_menu_icon_img = $this->_helper->getVerticalIconimageUrl($cat_model);
            if ($vc_menu_icon_img) {
                $iconImgUrl = (string)$cat_model->getImageUrl('vc_menu_icon_img');
                if ($iconImgUrl !== '') {
                    $html .= '<img class="menu-thumb-icon awa-mueller-icon awa-mueller-icon--image" src="' . $this->escapeUrl($iconImgUrl) . '" alt="' . $categoryNameAttr . '" loading="lazy"/>';
                }
            } else {
                $iconClass = $this->sanitizeClassList($vc_menu_font_icon);
                if ($iconClass !== '') {
                    $html .= '<em class="' . $this->escapeHtmlAttr('menu-thumb-icon awa-mueller-icon awa-mueller-icon--font ' . $iconClass) . '"></em>';
                }
            }

            $html .= '<span class="navigation__label">' . $categoryName . '</span>';

            if ($vc_menu_cat_label !== '' && isset($this->_verticalmenuConfig['cat_labels'][$vc_menu_cat_label])) {
                $labelKey = $this->sanitizeClassToken($vc_menu_cat_label);
                $labelText = $this->escapeHtml((string)$this->_verticalmenuConfig['cat_labels'][$vc_menu_cat_label]);
                $html .= '<span class="cat-label cat-label-' . $this->escapeHtmlAttr($labelKey) . '">' . $labelText . '</span>';
            }

            $html .= '</a>';

            if ($hasChildren) {
                $html .= '<div class="level0 submenu navigation__submenu navigation__inner-list navigation__inner-list--level1"'
                    . $custom_style
                    . ' data-level="1"'
                    . ' data-menu-id="' . $this->escapeHtmlAttr($menuToken . '-submenu') . '"'
                    . ' data-parent-id="' . $this->escapeHtmlAttr($menuToken) . '"'
                    . ' data-menu-title="' . $categoryNameAttr . '"'
                    . ' data-parent-menu="' . $this->escapeHtmlAttr($menuToken) . '">';

                if (($menu_type === 'fullwidth' || $menu_type === 'staticwidth') && $menu_top_content !== '') {
                    $html .= '<div class="menu-top-block">' . $this->getBlockContent($menu_top_content) . '</div>';
                }

                if (count($children) > 0 || (($menu_type === 'fullwidth' || $menu_type === 'staticwidth') && ($menu_left_content !== '' || $menu_right_content !== ''))) {
                    $html .= '<div class="row">';

                    $menu_left_width = $this->clampGridColumns($menu_left_width);
                    $menu_right_width = $this->clampGridColumns($menu_right_width);
                    $centerWidth = $this->clampGridColumns(12 - $menu_left_width - $menu_right_width);

                    if (($menu_type === 'fullwidth' || $menu_type === 'staticwidth') && $menu_left_content !== '' && $menu_left_width > 0) {
                        $html .= '<div class="menu-left-block col-sm-' . $menu_left_width . '">' . $this->getBlockContent($menu_left_content) . '</div>';
                    }

                    $html .= $this->getMuellerLevelOneItemsHtml(
                        $category,
                        $cat_model,
                        $children,
                        (int)$max_level,
                        $centerWidth,
                        $menu_type,
                        (int)$vc_menu_cat_columns,
                        $menuToken
                    );

                    if (($menu_type === 'fullwidth' || $menu_type === 'staticwidth') && $menu_right_content !== '' && $menu_right_width > 0) {
                        $html .= '<div class="menu-right-block col-sm-' . $menu_right_width . '">' . $this->getBlockContent($menu_right_content) . '</div>';
                    }

                    $html .= '</div>';
                }

                if (($menu_type === 'fullwidth' || $menu_type === 'staticwidth') && $menu_bottom_content !== '') {
                    $html .= '<div class="menu-bottom-block">' . $this->getBlockContent($menu_bottom_content) . '</div>';
                }

                $html .= '</div>';
            }

            $html .= '</li>';
        }

        $html .= $this->getCustomBlockHtml('after');

        return $html;
    }
}
