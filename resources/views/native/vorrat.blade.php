@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full items-center justify-center gap-3 bg-theme-background px-8">
    <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
    <native:text class="text-center text-base text-theme-on-surface">Alles auf der Liste.</native:text>
</native:column>
