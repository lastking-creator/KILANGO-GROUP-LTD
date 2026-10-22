<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @include('partials.aura-head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @include('partials.aura-layers')
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php
                    $role = auth()->user()->role;
                    $can = fn (array $roles) => in_array($role, $roles, true);
                @endphp

                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @if ($can(['admin', 'godown_keeper', 'outlet_manager']))
                <flux:sidebar.item icon="cube" :href="route('inventory')" :current="request()->routeIs('inventory')" wire:navigate>
                    {{ __('Inventory') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'outlet_manager']))
                <flux:sidebar.item icon="building-storefront" :href="route('outlets')" :current="request()->routeIs('outlets')" wire:navigate>
                    {{ __('Outlets') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'godown_keeper']))
                <flux:sidebar.item icon="building-office-2" :href="route('godowns')" :current="request()->routeIs('godowns')" wire:navigate>
                    {{ __('Godowns') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'godown_keeper', 'outlet_manager']))
                <flux:sidebar.item icon="arrows-right-left" :href="route('transfers')" :current="request()->routeIs('transfers')" wire:navigate>
                    {{ __('Stock transfers') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'outlet_manager', 'cashier']))
                <flux:sidebar.group expandable icon="shopping-cart" :heading="__('Sales')" :expanded="request()->routeIs('sales', 'sales.*')" class="grid">
                    <flux:sidebar.item :href="route('sales')" :current="request()->routeIs('sales')" wire:navigate>
                        {{ __('Point of sale') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('sales.history')" :current="request()->routeIs('sales.history')" wire:navigate>
                        {{ __('Sales history') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
                @if ($can(['admin', 'godown_keeper']))
                <flux:sidebar.group expandable icon="shopping-bag" :heading="__('Purchase')" :expanded="request()->routeIs('purchases', 'suppliers')" class="grid">
                    <flux:sidebar.item :href="route('purchases')" :current="request()->routeIs('purchases')" wire:navigate>
                        {{ __('Goods received') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('suppliers')" :current="request()->routeIs('suppliers')" wire:navigate>
                        {{ __('Suppliers') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
                @if ($can(['admin', 'outlet_manager']))
                <flux:sidebar.group expandable icon="banknotes" :heading="__('Finance')" :expanded="request()->routeIs('finance', 'expenses')" class="grid">
                    <flux:sidebar.item :href="route('finance')" :current="request()->routeIs('finance')" wire:navigate>
                        {{ __('Overview') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('expenses')" :current="request()->routeIs('expenses')" wire:navigate>
                        {{ __('Expenses') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
                @if ($can(['admin', 'outlet_manager', 'cashier']))
                <flux:sidebar.item icon="users" :href="route('customers')" :current="request()->routeIs('customers')" wire:navigate>
                    {{ __('Customers') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'outlet_manager', 'godown_keeper']))
                <flux:sidebar.item icon="chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate>
                    {{ __('Reports') }}
                </flux:sidebar.item>
                @endif
                @if ($can(['admin', 'godown_keeper', 'outlet_manager']))
                <flux:sidebar.group expandable icon="user-group" :heading="__('Employees')" :expanded="request()->routeIs('employees', 'attendance')" class="grid">
                    <flux:sidebar.item :href="route('employees')" :current="request()->routeIs('employees')" wire:navigate>
                        {{ __('Employees list') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('attendance')" :current="request()->routeIs('attendance')" wire:navigate>
                        {{ __('Attendance list') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
                @if ($can(['admin', 'godown_keeper', 'outlet_manager', 'cashier']))
                <flux:sidebar.group expandable icon="document-text" :heading="__('Documents')" :expanded="request()->routeIs('documents', 'documents.*')" class="grid">
                    <flux:sidebar.item :href="route('documents')" :current="request()->routeIs('documents')" wire:navigate>
                        {{ __('Print center') }}
                    </flux:sidebar.item>
                    @if ($can(['admin']))
                    <flux:sidebar.item :href="route('documents.company')" :current="request()->routeIs('documents.company')" wire:navigate>
                        {{ __('Company letterhead') }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
                @endif

                @if ($can(['admin']))
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        <flux:sidebar.item icon="user-circle" :href="route('users')" :current="request()->routeIs('users')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />
            <x-aura-toggle class="hidden px-2 lg:block" />
            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-aura-toggle compact />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
