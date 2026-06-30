package com.slid.borderreporting.dynamic.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.slid.borderreporting.dynamic.repo.DynamicFormRepository
import com.slid.borderreporting.dynamic.vm.DynamicFormsViewModel

class DynamicFormsViewModelFactory(
    private val repository: DynamicFormRepository
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        require(modelClass.isAssignableFrom(DynamicFormsViewModel::class.java)) {
            "Unknown ViewModel: ${modelClass.name}"
        }
        return DynamicFormsViewModel(repository) as T
    }
}
