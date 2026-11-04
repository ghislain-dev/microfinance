# kotlinx.serialization : garder les sérialiseurs générés des DTO.
-keepattributes *Annotation*, InnerClasses
-keep,includedescriptorclasses class com.microfinance.**$$serializer { *; }
-keepclassmembers class com.microfinance.** { *** Companion; }
-keepclasseswithmembers class com.microfinance.** { kotlinx.serialization.KSerializer serializer(...); }
