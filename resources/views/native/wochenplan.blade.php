@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full items-center justify-center gap-3 bg-theme-background px-8">
    <native:icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="48" class="text-theme-on-surface-variant" />
    <native:text class="text-center text-base text-theme-on-surface">Nichts geplant.</native:text>
</native:column>
