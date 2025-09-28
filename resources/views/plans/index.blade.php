<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Plans & Pricing') }}
        </h2>
    </x-slot>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-3xl font-bold text-gray-900">Choose Your Plan</h1>
            <p class="mt-4 text-lg text-gray-600">Scale your RAG capabilities with the right plan for your needs</p>

            <!-- Current Plan Badge -->
            <div class="mt-6">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium {{ $user->plan === 'enterprise' ? 'bg-purple-100 text-purple-800' : ($user->plan === 'pro' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                    Current Plan: {{ ucfirst($user->plan) }}
                </span>
            </div>
        </div>

        <!-- Usage Stats -->
        <div class="bg-white rounded-lg shadow mb-8 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Current Usage</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Token Usage -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Tokens</span>
                        <span class="text-sm text-gray-500">{{ number_format($user->tokens_used) }} / {{ number_format($user->tokens_limit) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, ($user->tokens_used / $user->tokens_limit) * 100) }}%"></div>
                    </div>
                </div>

                <!-- Document Usage -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Documents</span>
                        <span class="text-sm text-gray-500">{{ $user->documents_used }} / {{ $user->documents_limit }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(100, ($user->documents_used / $user->documents_limit) * 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <!-- Free Plan -->
            <div class="bg-white rounded-lg shadow {{ $user->plan === 'free' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-medium text-gray-900">Free</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold text-gray-900">$0</span>
                            <span class="text-base font-medium text-gray-500">/month</span>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">100 tokens/month</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">1 document</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">Basic queries only</span>
                        </li>
                    </ul>

                    <div class="mt-8">
                        @if($user->plan === 'free')
                            <button disabled class="w-full bg-gray-100 text-gray-500 py-3 px-4 rounded-md text-sm font-medium cursor-not-allowed">
                                Current Plan
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Pro Plan -->
            <div class="bg-white rounded-lg shadow {{ $user->plan === 'pro' ? 'ring-2 ring-blue-500' : '' }} relative">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-500 text-white">
                        Most Popular
                    </span>
                </div>
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-medium text-gray-900">Pro</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold text-gray-900">$29</span>
                            <span class="text-base font-medium text-gray-500">/month</span>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">5,000 tokens/month</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">50 documents</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">Advanced queries</span>
                        </li>
                    </ul>

                    <div class="mt-8">
                        @if($user->plan === 'pro')
                            <button disabled class="w-full bg-gray-100 text-gray-500 py-3 px-4 rounded-md text-sm font-medium cursor-not-allowed">
                                Current Plan
                            </button>
                        @else
                            <button class="w-full bg-indigo-600 text-white py-3 px-4 rounded-md text-sm font-medium hover:bg-indigo-700" onclick="alert('Stripe/Paddle integration needed')">
                                Upgrade to Pro - $29/month
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Enterprise Plan -->
            <div class="bg-white rounded-lg shadow {{ $user->plan === 'enterprise' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-medium text-gray-900">Enterprise</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold text-gray-900">$99</span>
                            <span class="text-base font-medium text-gray-500">/month</span>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">50,000 tokens/month</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-sm text-gray-600">Unlimited documents</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3 text-gray-600">All features + priority</span>
                        </li>
                    </ul>

                    <div class="mt-8">
                        @if($user->plan === 'enterprise')
                            <button disabled class="w-full bg-gray-100 text-gray-500 py-3 px-4 rounded-md text-sm font-medium cursor-not-allowed">
                                Current Plan
                            </button>
                        @else
                            <button class="w-full bg-purple-600 text-white py-3 px-4 rounded-md text-sm font-medium hover:bg-purple-700" onclick="alert('Stripe/Paddle integration needed')">
                                Upgrade to Enterprise - $99/month
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>


        <!-- FAQ Section -->
        <div class="mt-12">
            <h3 class="text-lg font-medium text-gray-900 mb-6">Frequently Asked Questions</h3>
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-900">What are tokens?</h4>
                    <p class="mt-2 text-sm text-gray-600">Tokens represent usage units for RAG queries. Basic queries use 1 token, advanced generation uses 5 tokens. Token limits reset monthly.</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Can I upgrade or downgrade anytime?</h4>
                    <p class="mt-2 text-sm text-gray-600">Yes! You can upgrade instantly. Downgrades take effect at the end of your current billing period.</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-900">What file formats are supported?</h4>
                    <p class="mt-2 text-sm text-gray-600">Free plans support PDF and TXT files. Pro and Enterprise plans support all formats including DOCX, XLSX, images with OCR, and more.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>