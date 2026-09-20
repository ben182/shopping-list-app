@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full items-center justify-center gap-3 bg-theme-background px-8">
    <native:icon :ios="Ios::Cart" :android="Android::ShoppingCart" :size="48" class="text-theme-on-surface-variant" />
    <native:text class="text-center text-base text-theme-on-surface">Liste ist leer.</native:text>
    <native:text class="text-center text-sm text-theme-on-surface-variant">
        Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.
    </native:text>
</native:column>
